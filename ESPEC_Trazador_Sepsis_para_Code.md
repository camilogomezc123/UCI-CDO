# Especificación para Claude Code — App «Paciente Trazador: Sepsis»

> **Para Claude Code:** este documento + el archivo `modelo_trazador_sepsis.json` describen una aplicación que reproduce, variable por variable, un libro de Excel de auditoría de un paciente trazador con sepsis. `modelo_trazador_sepsis.json` es la **fuente de verdad** de los campos (ids, tipos, catálogos, celda de Excel de origen). Implementa el formulario y los cálculos exactamente como se describe. **No omitas ninguna variable** y **respeta los catálogos** (listas) tal cual.

---

## Para Claude Code — stack, restricciones y forma de trabajar (LÉEME PRIMERO)

### Stack del proyecto
- **Laravel 12** con **Blade** y **Bootstrap 5**.
- Base de datos: **MySQL (XAMPP) en local** y **SQLite en producción**. Todo debe funcionar en **ambas**.
- Modelo principal: **`Paciente`**.

### Qué ya existe (no lo reinventes)
- Controlador **`PacienteController.php`**, vista **`pacientes/index.blade.php`**, ruta **`pacientes.index`**.
- **No crees archivos nuevos a menos que sea necesario.** Reutiliza y extiende lo existente.

### Restricciones técnicas (obligatorias)
- Las **migraciones deben funcionar en MySQL y SQLite**.
- **No uses `MODIFY COLUMN`.** Para cambios de columnas existentes prefiere agregar columnas nuevas; usa `->change()` solo si está `doctrine/dbal`.
- **No uses `ENUM`.** Usa **strings** + **validación en PHP** (FormRequest con `Rule::in($catalogo)`).
- Tipos seguros en migraciones: `string`, `boolean`, `integer`, `json`, `timestamp()->nullable()`. JSON funciona en MySQL 5.7+ y SQLite (Laravel lo castea a array).

### Excel: solo exportación consolidada (no se importan pacientes)
- La **captura de pacientes es por formulario** (prellenando desde el censo de UCI); **no se importa Excel** de pacientes.
- **Sí se exporta a Excel**: reportes **consolidados mensual, trimestral y anual** de los indicadores de los trazadores (para tener data confiable). Para esto sí puedes usar **`maatwebsite/excel` (Laravel Excel)** — **única dependencia permitida y solo para exportar**.
- El `.xlsx` original y `modelo_trazador_sepsis.json` son **solo el plano** del formulario y los cálculos.

### Modularidad (a futuro habrá muchas patologías)
- El trazador de **Sepsis** debe quedar en su **módulo/ventana independiente**. La arquitectura debe permitir **agregar nuevas patologías sin reescribir** el resto.
- Habrá un contenedor **“Trazadores”** que lista los tipos disponibles; cada tipo (Sepsis hoy) abre su **propia página/formulario**, definido por su propio modelo JSON e indicadores.

### Proyecto ya iniciado (revísalo antes de crear)
- Ya hay algo construido en **`C:\xampp\htdocs\Paciente trazador`**. **Revisa primero ese proyecto** y **reutiliza/extiende** lo que sirva; no rehagas lo que ya existe.

### Lo que NO debes hacer
- **No cambies el diseño visual ni el layout** existente (no toques estilos ni la estructura de `pacientes/index.blade.php`). Haz cambios **acotados** al alcance pedido.
- No agregues dependencias ni reescribas lo que ya funciona.

### Cómo te paso un error
Cuando algo falle, pego el **mensaje de error completo** (no un resumen ni una captura), **incluyendo el stack trace** y el archivo/línea. Con eso corriges el punto exacto sin tocar lo demás.

---

## 0. Fidelidad al Excel — el Excel es solo el modelo de referencia

Este modelo se generó **leyendo el Excel directamente** y con el Excel ya **limpiado**. Puntos clave, reflejados en `modelo_trazador_sepsis.json` → `fidelidad_excel`:
- **Desenlace**: opciones reales = *Vivo - egreso UCI / Vivo - traslado / Fallecido*.
- **Metas de manejo**: 8 metas **ya nombradas** en el Excel (Llenado capilar < 3 s, PAM 65–95, Diuresis, Lactato/aclaramiento, SatO₂, Glucometría, Temperatura, EVA); el % se calcula automático.
- **Elemento F (educación)**: **unificado** — Médico, Fisioterapia y Auxiliar usan numerador/denominador, igual que el resto del ABCDEF.
- **Fechas/horas**: el Excel las guarda como texto; en la app usa selector de fecha-hora.
- **Erratas corregidas en el Excel**: “Turmo” → presencia de delirium · “FASTHUB” → FAST-HUG.

---

## 1. Objetivo

Construir una pantalla/registro de **un paciente trazador** que:
1. Capture todas las variables del Excel (admisión, recorrido del Código Sepsis, Bundle ABCDEF, y dos encuestas de funcionalidad: ANTES y DESPUÉS).
2. Calcule automáticamente los **indicadores** (sepsis S1–S8, ABCDEF, escalas EQ-5D-5L · WHODAS 2.0 · Barthel · Clinical Frailty Scale) y un **semáforo** y **puntuación global**.
3. Se llene con la **misma lógica del modelo**: campos amarillos = entrada; campos grises = calculados (solo lectura); listas desplegables idénticas.
4. **Excel solo para reportes**: la captura es por **formulario** (prellenando desde el censo); **no se importa Excel** de pacientes, pero **sí se exporta** un consolidado de indicadores (mensual/trimestral/anual).

Un (1) registro = un (1) paciente.

---

## 2. Principios NO negociables

- **Todas las variables del Excel deben existir** en la app. Usa `modelo_trazador_sepsis.json` como **inventario exacto** (campos de Datos, Fases I–III, metas, todos los indicadores ABCDEF y las 22 preguntas de la encuesta). No dejes ninguna por fuera.
- **Mismos catálogos** (opciones de cada lista) y **misma forma de llenar** (desplegables donde el Excel los tiene; fecha-hora donde el Excel pide hora).
- **Regla “no evidenciado”**: lo que no se documenta cuenta como **no cumplido**. Un campo vacío no se asume cumplido.
- **Campos calculados = solo lectura.** El usuario nunca los edita.
- **Fechas y horas** en formato `DD/MM/AAAA HH:MM`. Varios indicadores miden **minutos desde el “tiempo cero”** (la hora de activación del Código Sepsis); sin la hora no se pueden calcular.

---

## 3. Tipos de dato y catálogos

Tipos usados (campo `tipo` en el JSON): `datetime`, `date`, `number`, `text`, `select`.
Para `select`, el campo trae `catalogo` (id de una lista en `catalogos`) o, si es propio, `opciones`.

**Catálogos clínicos** (en `catalogos` del JSON):
`SI_NO`, `SI_NO_NE` (Sí/No/No evidenciado), `SI_NO_NA` (Sí/No/No aplica), `SEXO`, `SERVICIO`, `METODO_VOLUMEN`, `VASOPRESOR`, `FENOTIPO`, `DESENLACE` (Vivo - egreso UCI / Vivo - traslado / Fallecido), `CUMPLIMIENTO_ELEMENTO` (Cumple/Cumple parcial/No cumple/No evidenciado), `SUBTIPO_DELIRIUM`.

**Catálogos de la encuesta** (el número inicial de cada opción es el **código/puntaje**; en el JSON vienen como `{code, label, texto_excel}`):
- `DIF5` (dificultad 1–5), `AYUDA5` (nivel de ayuda 1–5), `DOLOR5` (1–5), `ANIMO5` (1–5).
- Barthel por ítem (el code es el puntaje): `BART_ALIM` (10/5/0), `BART_ASEO` (5/0), `BART_RETR` (10/5/0), `BART_DEPOS` (10/5/0), `BART_MICC` (10/5/0), `BART_TRAS` (15/10/5/0), `BART_ESCAL` (10/5/0).
- `CFS_L` (fragilidad 1–9).

> Implementación sugerida: renderiza cada `select` desde su catálogo; al guardar, almacena **el código** (no el texto) para los catálogos con `code`, así los cálculos son directos.

---

## 4. Estructura del formulario (en este orden, como el Excel)

Las secciones están en `secciones[]` del JSON, cada una con sus `campos`. Resumen:

### 4.1 Datos del paciente (`datos_paciente`)
Identificación y estancia. Incluye 2 **calculados**: `Estancia en UCI (días)` y `Días de ventilación mecánica` (diferencia de fechas). Campos como `Sexo`, `Servicio de ingreso`, `Requirió VM`, `Reintubación <48h`, `Reingreso ≤72h`, `Desenlace` son `select`; las fechas son `datetime`.

### 4.2 Evaluación Integrada — recorrido clínico
Tres fases en orden + metas:
- **Fase I · Activación** (`fase1_activacion`): hora de **ACTIVACIÓN (tiempo cero)**, NEWS2, SOFA, PAM, PAD, llenado capilar, lactato inicial.
- **Fase II · Bundle 1 hora** (`fase2_bundle_1h`): cada **hora** registrada calcula “minutos desde el tiempo cero”. Lactato (toma+valor), llenado capilar, hemocultivos (tomados/solicitados), antibiótico (hora 1ª dosis + cuál), cristaloides (indicados/volumen/evaluación/método), vasopresor (formulación indicada / hora de inicio / fármaco 1 y 2 / dosis), azul de metileno.
- **Fase III · Reevaluación** (`fase3_reeval`): lactato y llenado de control, fenotipo, ecografía, VTI, VEXUS, ajuste antimicrobiano, **control del foco** (identificado / realizado <6 h).
- **Metas de manejo** (`metas_manejo`): 8 filas Sí/No/No evidenciado (informativo; calcula % de metas cumplidas). Las 8 metas ya están nombradas en el Excel.

### 4.3 Bundle ABCDEF (Fase IV)
En `indicadores_abcdef` del JSON. **Cada indicador “ratio” se llena con dos números: `numerador` y `denominador`** (p. ej. turnos cumplidos / turnos evaluados). Además, por indicador: `evidencia` (texto) y `oportunidad_mejora` (texto). Por **elemento (A–F)**: un `cumplimiento_elemento` (catálogo `CUMPLIMIENTO_ELEMENTO`). Indicadores: A1–A3, B1–B3, C1–C4, D1–D4 (D2 informativo: presencia de delirium; subtipo con `SUBTIPO_DELIRIUM`), E1–E3 (E2 informativo: nivel 1–6), F (notas de educación: **Médico, Fisioterapia y Auxiliar = numerador/denominador**) y FAST-HUG.

### 4.4 Encuesta de funcionalidad (`encuesta`) — **dos instancias: ANTES y DESPUÉS**
Crea dos copias por paciente (`antes` = basal/ingreso; `despues` = egreso/seguimiento), misma estructura. Cada una tiene:
- **Datos del encuestado**: folio, fecha de llamada, nombre/ID, edad, sexo, fecha de ingreso/intervención, aplicada por, ¿quién responde?
- **22 preguntas** (`preguntas[]`): cada una con `texto_leer` (lo que el encuestador lee), `catalogo` (la escala de respuesta) y la celda de origen. Las preguntas son de selección 1–5 / Barthel / 1–9, salvo Q21 (salud 0–100, número).

---

## 5. Cálculo de indicadores (implementar exactamente)

> Convención: cada indicador produce un **valor** ∈ {número 0–100, `"N/A"`, vacío}. `min(a,b)` = minutos entre dos fechas = `(b − a)` en minutos.

### 5.1 Sepsis (`indicadores_sepsis` en el JSON)
- **S1 Activación** (meta 85): `100` si hay `tiempo_cero`, si no `0`.
- **S2 Lactato ≤60** (meta 90): sea `m = min(tiempo_cero, hora_toma_lactato_faseII)`. `100` si `m ≤ 60`, si no `0`. Vacío si falta alguna hora.
- **S3 Antibiótico ≤60** (meta 90): `m = min(tiempo_cero, hora_1a_dosis)`. `100` si `m ≤ 60`, si no `0`.
- **S4 Hemocultivos previos** (meta 85): `100` si `hemocultivos_tomados == "Sí"`, si no `0`.
- **S5 Bundle 1 hora** (meta 75): `100` si **todo** se cumple, si no `0` (vacío si faltan tiempo_cero/lactato/atb/hemocultivos):
  `lactato_min ≤ 60` **y** `atb_min ≤ 60` **y** `hemocultivos_tomados == "Sí"` **y** (`formulacion_vaso == "Sí"` ⇒ `vaso_inicio_min ≤ 60`) **y** (`liquidos_indicados == "Sí"` ⇒ `volumen_cristaloides > 0`).
- **S6 Vasopresor oportuno** (meta 90): si `formulacion_vaso == "No"` ⇒ `"N/A"`; si `"Sí"` ⇒ `100` si `vaso_inicio_min ≤ 60`, si no `0`.
- **S7 Control del foco** (meta 82.5): si `foco_identificado == "No"` ⇒ `"N/A"`; si `"Sí"` ⇒ `100` si `foco_realizado_<6h == "Sí"`, `0` si `"No"`.
- **S8 Mortalidad**: informativo (muestra el desenlace; no se puntúa).

### 5.2 ABCDEF
- Indicador **ratio**: `valor = numerador / denominador * 100` (vacío si denominador 0 o falta un número).
- Indicadores **informativos** (D2, E2): se registran y se muestran, **no** se puntúan.

### 5.3 Semáforo (`semaforo` en el JSON)
- Por indicador, con su `meta_pct` y `piso_pct`:
  - **Verde**: `valor ≥ meta`
  - **Amarillo**: `piso ≤ valor < meta`
  - **Rojo**: `valor < piso`
  - **“— sin dato”**: valor vacío (cuenta como no cumplido)
  - **“No aplica”**: `valor == "N/A"` (se **excluye** del promedio)
- **Agregados**:
  - `adherencia_reanimacion_pct` = promedio de S1..S7 puntuables (excluye N/A, sin dato, informativos).
  - `adherencia_abcdef_pct` = promedio de los ABCDEF ratio puntuables.
  - `puntuacion_global_pct` = promedio de **todos** los puntuables (sepsis + ABCDEF).
  - Banda global: **Verde > 90 · Amarillo 70–89 · Rojo < 70**.

### 5.4 Escalas de la encuesta (`escalas_encuesta` en el JSON)
Usa el **code** de cada respuesta (1–5, puntos de Barthel, 1–9).
- **EQ-5D-5L**: dimensiones → movilidad=`Q1`, cuidado_personal=`max(Q5,Q6)`, actividades=`max(Q12,Q13)`, dolor=`Q19`, ansiedad=`Q20`. Perfil = concatenar los 5 dígitos en ese orden. `suma_niveles` = suma (5–25, **más alto = peor**). `EQ-VAS` = `Q21` (0–100). *(El índice de utilidad requiere el value set de Colombia; no se calcula aquí.)*
- **WHODAS 2.0 (12 ítems)**: ítems = `[Q2,Q12,Q14,Q16,Q20,Q15,Q1,Q6,Q5,Q17,Q18,Q13]`. `suma` (12–60). `indice_0_100 = (suma − 12) / 48 * 100` (**más alto = más discapacidad**).
- **Barthel (0–100)**: directos → alimentación=`Q8`, aseo=`Q7`, retrete=`Q9`, deposición=`Q10`, micción=`Q11`, traslado=`Q3`, escaleras=`Q4` (cada uno aporta el **code** como puntos). Derivados:
  - baño = de `Q6`: 5 si `code≤2`, si no 0.
  - vestirse = de `Q5`: 10 si `code≤2`, 5 si `code==3`, 0 si `code≥4`.
  - deambulación = de `Q1`: 15 si `code≤2`, 10 si `code==3`, 5 si `code==4`, 0 si `code==5`.
  - `total` = suma de los 10 (**más alto = más independiente**). Grados: 100 indep · 60–95 leve · 40–55 moderada · 20–35 grave · <20 total.
- **Clinical Frailty Scale**: `Q22` (code 1–9) + categoría (label de `CFS_L`). **Más alto = más frágil.**
- **Comparativo**: calcula cada escala para `antes` y `despues` y muestra la diferencia (`despues − antes`).

> Recuerda al mostrar resultados: en **EQ-5D, WHODAS y CFS más alto es peor**; en **Barthel más alto es mejor**.

---

## 6. Comportamiento de la interfaz (igual al Excel)

- **Campos amarillos** = editables; **grises** = calculados (deshabilitados). Marca visualmente la diferencia.
- **Listas desplegables** idénticas a los catálogos (no permitir escribir libre donde hay lista; o si lo permites, que un valor fuera de lista cuente como vacío, nunca rompa el cálculo).
- **Tiempos**: inputs de fecha-hora; junto a cada hora del bundle, muestra los “minutos desde el tiempo cero” (auto).
- **Semáforo**: pinta cada indicador de verde/amarillo/rojo según la regla; tablero con adherencias por fase y puntuación global.
- **Encuesta**: muestra el `texto_leer` de cada pregunta (es lo que el encuestador lee por teléfono) y la lista de respuesta. Dos pestañas: ANTES y DESPUÉS.
- **Validaciones suaves**: no bloquees el guardado por campos faltantes; márcalos como “sin dato” (la auditoría los penaliza, pero deben poder llenarse después).

---

## 7. Flujo del paciente trazador (ciclo de vida y estados)

El módulo de trazador **no parte de cero**: toma pacientes que ya están cargados en el censo de UCI.

1. **Marcar como trazador.** En la lista/censo de pacientes de UCI, cada paciente tiene la acción **“Marcar como paciente trazador”** con selección de **tipo** (por ahora *Sepsis*; deja el tipo extensible para futuros trazadores). Al marcarlo:
   - Se crea **automáticamente** un registro de trazador en el módulo Trazador, **duplicando** al paciente.
   - El formulario se **prellena** con todos los datos que el paciente YA tiene (identificación, edad, sexo, servicio, fechas de ingreso, diagnóstico, comorbilidades y cualquier dato clínico ya disponible).
   - El usuario **solo diligencia los campos que falten**. Los campos prellenados quedan **editables** (corrección de errores).
   - **Mapeo:** reutiliza los mismos `id` de campo entre el censo y el trazador. Si el censo tiene valor para un campo del trazador → prellénalo; si no → vacío para diligenciar.

2. **Parte inicial.** Lo que se diligencia al inicio = `datos_paciente` + Fases I–III + `abcdef` + **Encuesta ANTES** (basal). Con esto se calculan los indicadores de reanimación, ABCDEF y la funcionalidad basal.

3. **Guardar → pasa a estadísticas + inicia conteo de 90 días.** Al **guardar** la parte inicial:
   - El paciente **sale de la bandeja de trazadores activos** (para no saturar la vista) y pasa a **“Estadísticas de paciente trazador”**. El caso queda **abierto**, en estado *seguimiento a 90 días*.
   - Inicia un **conteo de 90 días** desde la fecha de guardado. Guarda `fecha_guardado_inicial` y `fecha_objetivo_despues = fecha_guardado_inicial + 90 días`.

4. **Día 90 → reaparece solo para la Encuesta DESPUÉS.** Al cumplirse los 90 días, el paciente **reaparece** en una bandeja de **“pendientes de encuesta DESPUÉS”**. Lo **único** que se diligencia ahí es la **Encuesta DESPUÉS** (seguimiento); no se vuelve a pedir lo clínico.

5. **Cerrar el caso.** Al guardar la Encuesta DESPUÉS se calcula el **Comparativo** (antes vs después) y el caso queda **CERRADO/completo**. Esta es la **única** forma de cerrar el caso.

6. **Editar siempre.** En cualquier estado debe existir la opción de **abrir y modificar** el paciente/trazador para **corregir errores**; al guardar, **recalcula** los indicadores.

**Máquina de estados (campo `estado`):**
- `EN_UCI` — en el censo, no marcado como trazador.
- `TRAZADOR_INICIAL` — marcado; diligenciando la parte inicial (visible en bandeja de activos).
- `SEGUIMIENTO_90D` — parte inicial guardada; en estadísticas; conteo de 90 días corriendo; **oculto** de la bandeja de activos.
- `PENDIENTE_DESPUES` — cumplidos los 90 días; reaparece solo para la Encuesta DESPUÉS.
- `CERRADO` — Encuesta DESPUÉS guardada; caso completo.

**Transiciones:** `EN_UCI` →(marcar)→ `TRAZADOR_INICIAL` →(guardar inicial)→ `SEGUIMIENTO_90D` →(día 90)→ `PENDIENTE_DESPUES` →(guardar después)→ `CERRADO`. (Editar disponible en todos; recalcula.)

**Bandejas/listas que debe ofrecer la UI:**
- **Censo de UCI** (con acción “marcar como trazador”).
- **Trazadores activos** (`TRAZADOR_INICIAL`).
- **Estadísticas de trazador** (`SEGUIMIENTO_90D` + `CERRADO`, con sus indicadores).
- **Pendientes de encuesta DESPUÉS** (`PENDIENTE_DESPUES`), con la fecha objetivo y aviso al cumplir 90 días.

---

## 8. Arquitectura en Laravel 12 (concreta)

**Sin Excel.** Los datos vienen del censo (modelo `Paciente`) y del formulario del trazador. El `.xlsx`/JSON son solo el plano.

**Datos / migraciones (MySQL + SQLite), pensadas para MÚLTIPLES patologías.** Una sola tabla genérica `trazadores` (no una por patología):
- `id`, `paciente_id` (FK), `tipo_trazador` (string: 'sepsis', extensible), y **estado/fechas** como strings/timestamps (NO ENUM): `estado` (string), `fecha_guardado_inicial`/`fecha_objetivo_despues`/`fecha_cierre` (timestamp nullable).
- `datos` (JSON, cast a array): TODO el contenido del formulario según el modelo del tipo (fase1, fase2, fase3, metas, abcdef, encuesta_antes, encuesta_despues, datos_paciente_extra…).
- `resultados` (JSON): indicadores calculados.
- **Agregar una patología nueva = nuevo `tipo_trazador` + su modelo JSON + su service de indicadores + su vista; SIN cambiar el esquema.**
- Ejemplo: `$table->string('tipo_trazador');` · `$table->string('estado')->default('TRAZADOR_INICIAL');` · `$table->json('datos')->nullable();` · `$table->json('resultados')->nullable();` · `$table->timestamp('fecha_objetivo_despues')->nullable();`.

**Módulo independiente por patología.** Un contenedor `Trazadores` (índice de tipos disponibles) y una **página/ventana propia por tipo** (ruta tipo `/trazadores/sepsis`). Sepsis es el primer tipo; cada tipo carga su modelo JSON y su `IndicadoresService` correspondiente. Para este módulo **sí puedes crear un `TrazadorController`** (está justificado por ser módulo independiente); deja `PacienteController` para el censo y la acción “marcar como trazador”.

**Exportación consolidada a Excel.** Reportes **mensual, trimestral y anual** con los indicadores de los trazadores **cerrados** en el periodo, **agrupables por tipo de patología** (hoja resumen + detalle por indicador). Usa `maatwebsite/excel`.

**Catálogos (sin ENUM).** Carga `modelo_trazador_sepsis.json` (o un `config/trazador.php`) en un service/singleton. Las listas de Blade se llenan desde ahí; la validación usa `Rule::in($catalogo)` en un **FormRequest** (nunca ENUM en la BD).

**Controlador (`PacienteController` ya existe).** Agrega métodos **acotados** (sin crear controladores nuevos salvo que crezca demasiado): marcar como trazador, mostrar/guardar el formulario inicial, bandeja de pendientes de DESPUÉS, guardar la encuesta DESPUÉS (cierre) y editar.

**Rutas.** Parte de `pacientes.index` (ya existe); agrega rutas acotadas para las acciones del trazador.

**Vistas Blade + Bootstrap 5.** Reutiliza `pacientes/index.blade.php` **sin cambiar su diseño**. El formulario del trazador en vista(s) Blade nuevas con Bootstrap 5: campos editables (amarillos) habilitados; calculados (grises) como `readonly`/`disabled`. Genera secciones y desplegables **recorriendo el JSON** (`secciones`, `campos`, `preguntas`, `catalogos`).

**Cálculo de indicadores (PHP).** Un service por tipo (`IndicadoresSepsis`, implementando una interfaz común `IndicadoresTrazador`) que reciba el registro y devuelva sepsis/ABCDEF/escalas/semáforo/global según la sección 5; guarda el resultado en `resultados` al guardar/editar. Así cada patología futura trae su propio service.

**Conteo de 90 días.** Al guardar la parte inicial: `fecha_guardado_inicial = now()`, `fecha_objetivo_despues = now()->addDays(90)`, `estado = 'SEGUIMIENTO_90D'`. Para “pendientes de DESPUÉS”: `where('estado','SEGUIMIENTO_90D')->where('fecha_objetivo_despues','<=',now())` (o un scope). Al guardar la encuesta DESPUÉS: `estado='CERRADO'`, `fecha_cierre=now()`, recalcula el comparativo.

**Editar siempre.** Toda vista permite reabrir y editar; al guardar, recalcula indicadores. Los campos prellenados son editables (corrección de errores).

---

## 9. Criterios de aceptación (checklist para Code)

- [ ] Están **todas** las variables del JSON (compara contra `secciones`, `indicadores_abcdef`, `preguntas`). Ninguna falta.
- [ ] Cada `select` usa exactamente las opciones de su catálogo.
- [ ] Las fechas son fecha-hora; los “minutos desde el tiempo cero” se calculan.
- [ ] S1–S8 dan el resultado de la sección 5.1 (incluye N/A en S6/S7 cuando corresponde).
- [ ] ABCDEF: ratio = num/den·100; D2 y E2 informativos.
- [ ] Semáforo y puntuación global con las bandas indicadas; N/A e informativos excluidos del promedio.
- [ ] EQ-5D / WHODAS / Barthel / CFS dan los mismos valores que el Excel (ver casos de prueba abajo).
- [ ] Campos calculados son solo lectura.
- [ ] La encuesta existe en dos momentos (ANTES/DESPUÉS) y el comparativo muestra la diferencia.

- [ ] Desde el censo de UCI se puede marcar un paciente como trazador (tipo Sepsis) y se crea su registro **prellenado**.
- [ ] El formulario prellena los datos existentes y **solo pide los faltantes**; los prellenados son **editables**.
- [ ] Al guardar la parte inicial, el paciente **sale de la bandeja activa**, pasa a **estadísticas** y arranca el **conteo de 90 días**.
- [ ] A los **90 días** reaparece **solo** para la Encuesta DESPUÉS; al guardarla, el caso se **cierra**.
- [ ] Se puede **editar** el paciente/trazador en cualquier estado y los indicadores se **recalculan**.

- [ ] **No hay import/export de Excel**; los datos se capturan por formulario y viven en la BD.
- [ ] Las **migraciones corren en MySQL y SQLite**; **sin `ENUM` ni `MODIFY COLUMN`** (catálogos validados en PHP).
- [ ] **No se modificó el diseño/layout** existente (`pacientes/index.blade.php` intacto); cambios acotados.

- [ ] El trazador de Sepsis está en un **módulo/ventana independiente** y la estructura permite **agregar patologías** sin cambiar el esquema (tabla `trazadores` genérica con `tipo_trazador`).
- [ ] Existe **exportación a Excel consolidada mensual, trimestral y anual** de los indicadores (agrupable por tipo).
- [ ] Se **revisó y reutilizó** el proyecto existente en `C:\xampp\htdocs\Paciente trazador`.

### Casos de prueba (verifican el cálculo)
1. **Sepsis**: tiempo_cero 14:00; lactato 14:20; antibiótico 14:40; hemocultivos_tomados=Sí; vaso=No; foco_identificado=No → S1=100, S2=100 (20 min), S3=100 (40 min), S4=100, S5=100, S6=“N/A”, S7=“N/A”; adherencia de reanimación = 100 %.
2. **Encuesta** (códigos): Q1=3, Q5=2, Q6=3, Q12=3, Q13=2, Q19=3, Q20=2, Q21=65, Q22=4; Q2=2,Q14=1,Q15=2,Q16=3,Q17=1,Q18=2; Barthel: Q8=10,Q7=5,Q9=10,Q10=10,Q11=5,Q3=10,Q4=5 →
   - EQ-5D perfil **33332**, suma **14**, VAS **65**.
   - WHODAS suma **26**, índice **29.2**.
   - Barthel total **75** → “Dependencia leve”.
   - CFS **4** (Vulnerable).

---

## 10. Notas

- Las etiquetas del ABCDEF ya están corregidas en el Excel-modelo y en el JSON (“Turmo” → presencia de delirium · “FASTHUB” → FAST-HUG).
- El “índice de utilidad” de EQ-5D-5L (valor de 0 a 1) **no** está en este modelo: requiere el set de valores (tariff) de Colombia; impleméntalo aparte si lo necesitas.
- Mantén la regla de oro: **amarillo se llena, gris se respeta; lo no documentado no se asume**.
