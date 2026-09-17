---
name: revisar-nuvads
description: "Solo para Codex. Claude Code NO usa este skill (tiene el subagente revisor-nuvads para la misma función). En Codex, usar al cerrar una tarea de programación: lanza un subagente de solo lectura que audita los cambios contra los acuerdos del proyecto siguiendo el procedimiento de .claude/agents/revisor-nuvads.md."
---

# Revisión de código (exclusivo de Codex)

Claude Code no debe usar este skill: dispone del subagente `revisor-nuvads`.

Procedimiento:

1. Lanzar un subagente con instrucciones explícitas de no modificar archivos: solo lee y reporta.
2. El subagente lee `.claude/agents/revisor-nuvads.md` en la raíz del proyecto, ignora su frontmatter (name, description, tools: metadata de otra herramienta) y ejecuta el procedimiento del cuerpo al pie de la letra: fuentes de verdad, alcance, pasos y formato del reporte.
3. Pasarle al subagente el alcance de la revisión (cambios pendientes, rango de commits o lista de archivos) y las excepciones a las reglas que el usuario haya aprobado durante la tarea.
4. El resultado es el reporte del subagente. No se modifica ningún archivo.
