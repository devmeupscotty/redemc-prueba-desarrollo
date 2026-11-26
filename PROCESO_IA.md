# Herramientas de IA Utilizadas y para que partes

Desarrollé el proyecto utilizando únicamente Claude Code como herramienta asistente de IA.
Concretamente, utilicé el modelo Opus 5.5 medium con el cual, a partir de inputs correspondientes a los requisitos de la prueba y comentarios personales, y los modos Ask User Questions y Plan Mode, desarrollé un plan completo para la implementación de la solución. Luego de la creación del plan, trabajé junto con Claude (también con el modelo Opus 5.5 medium) en el modo Manual (Ask before edits) para la implementación de la solución, donde el trabajo se basó en un constante ida y vuelta con el agente, discutiendo sus propuestas, explorando alternativas y llevando una discusión constante con el mismo.
Utilicé a su vez varios subagentes expertos a modo de reviewers de código, para mejorar en varias instancias la escalabilidad, prolijidad y seguridad del código, así como aspectos clave de buenas prácticas de código, patrones SOLID, GRASP, y clean code.

# Prompts que resultaron clave

## Prompt 1

Task: Code-quality review by a subagent, then implementation

Step 1: Launch a review subagent (read-only, it must not modify files)
Give it this brief:

You are a senior code reviewer, expert in clean code, SOLID, GRASP, design patterns and idiomatic use of this project's stack.

First, read the project's original requirements (README, spec, task description) so you can judge the code against what was actually asked. Then review the whole codebase for:
1. **Requirement mismatches:** missing, incomplete or different behavior (report these first)
2. **Bugs and robustness:** edge cases, error handling, validation
3. **Design:** SOLID/GRASP violations, coupling, cohesion, misplaced responsibilities
4. **Patterns:** where one would genuinely simplify the code, or where one is misused
5. **Clean code:** naming, duplication, function size, dead code, readability
6. **Tests and structure:** coverage gaps, fragile tests, module organization

Rules: point every finding to a specific file and line, explain why it matters *here*, and be pragmatic. Don't propose changes that add complexity without a clear benefit, and don't invent problems.

Return:
1. A short executive summary
2. A findings table: ID | Severity | Category | Location | Problem | Principle | Fix
3. An implementation plan: findings grouped into small, ordered units, each meant to be ONE commit, with a suggested commit message, files affected, the changes and how to verify them. Bugs and requirement fixes first, then design, then polish.

Step 2: Validate and propose
Review the subagent's findings critically and discard anything wrong or not worth it. Then show me the summary and the plan as a proposal, noting what you discarded and why.

---

## Prompt 2 

Treat this as a collaboration: you propose, I decide. For every meaningful decision (technology, architecture, structure, trade-offs), give me the options, the pros and cons of each, and your recommendation, then let me choose. Don't lock in any decision on your own.

Work in two phases, and don't move to the next one until I confirm:

1. **Analyze & clarify:** Read the requirements carefully. Then use Ask User Questions to go through every ambiguity, open decision or gap: scope, tech stack, architecture, constraints, edge cases and acceptance criteria. Group related questions and start with the ones that block other decisions. For each one, suggest options and say which you'd pick and why.

2. **Plan:** Enter plan mode and draft a proposed implementation plan: architecture, project structure, key components, data models, technologies, testing strategy, and an ordered breakdown into small, modular commits. Present it as a proposal. Point out the parts where there are reasonable alternatives, and we'll iterate until I'm happy with it.

# Algo que la IA propuso y corregí

Opus propuso dejar el módulo de JS como un único archivo app.js, lo cual tuve que descartar rotundamente y corregir. El motivo es sencillo, si bien la cantidad de líneas no era disparatada, había varias responsabilidades juntas en el archivo app.js, por lo que no cumplía con los principios GRASP.
Para corregirlo, dividí el archivo en varios archivos diferentes. Anteriormente, este archivo se encargaba de: Llamadas AJAX (createApi), funciones del formulario (validación, envío, errores) y listado (render seguro, paginado). Luego de la corrección, el módulo de JS pasó a estar dividido en varios archivos que anteriormente no existían (api.js, form.js, list.js), aumentando la prolijidad, legibilidad y cumplimiento de principios GRASP

# Algo que aprendí

Si bien ya tenía bastante experiencia realizando pruebas unitarias, nunca había utilizado PHPUnit como framework para las mismas. Creo que el uso de la herramienta me ayudó a profundizar en mi conocimiento general de pruebas unitarias, y también me dio la posibilidad de explorar una nueva forma de trabajar, con un framework al cual no estaba acostumbrado y en un lenguaje con el que ya había tenido contacto pero no formaba parte de mi stack principal. Creo que haberle dado uso elevó mi conocimiento sobre cómo trabajar con PHP, cómo realizar buenas pruebas unitarias y cómo darle un uso eficiente al framework.