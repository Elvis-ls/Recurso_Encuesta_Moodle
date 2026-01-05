<?php
/**
 * ARCHIVO: db/install.php
 * 
 * PROPÓSITO: Define las preguntas iniciales de la encuesta
 * Este archivo se ejecuta cuando instalas el módulo por primera vez
 * Aquí es donde crearemos las 5 preguntas de satisfacción
 */

function xmldb_survey_install() {
    global $DB;

    // 1. CREAR EL TEMPLATE DE LA ENCUESTA
    // ====================================
    // Este es el "molde" de nuestra encuesta de satisfacción
    $template = new stdClass();
    $template->course = 0;  // 0 = template (no es una encuesta real todavía)
    $template->template = 0; // 0 = es un template base
    $template->days = 0;
    $template->timecreated = time();
    $template->timemodified = time();
    $template->name = 'satisfactionname'; // Nombre clave para traducir
    $template->intro = 'satisfactionintro'; // Introducción clave para traducir
    $template->questions = '1,2,3,4,5'; // IDs de las preguntas (las crearemos abajo)
    
    $DB->insert_record('survey', $template);

    // 2. CREAR LAS PREGUNTAS
    // =======================
    // Cada pregunta tiene:
    // - text: Texto completo de la pregunta (se traduce en lang/en/survey.php)
    // - shorttext: Versión corta (para reportes)
    // - type: Tipo de pregunta
    //   * 0 = texto libre (respuesta abierta)
    //   * 1 = opción única (como radio buttons)
    // - options: Las opciones de respuesta (para type=1)

    // PREGUNTA 1: Calificación general (1-5)
    $question1 = new stdClass();
    $question1->text = 'satisfaction_q1'; // "¿Cómo calificarías el curso en general?"
    $question1->shorttext = 'satisfaction_q1_short'; // "Calificación general"
    $question1->multi = ''; // No tiene subpreguntas
    $question1->intro = ''; // No necesita introducción especial
    $question1->type = 1; // Tipo 1 = selección única
    $question1->options = 'satisfaction_scale5'; // Escala 1-5
    $DB->insert_record('survey_questions', $question1);

    // PREGUNTA 2: Claridad del contenido (1-5)
    $question2 = new stdClass();
    $question2->text = 'satisfaction_q2'; // "¿El contenido fue claro y comprensible?"
    $question2->shorttext = 'satisfaction_q2_short';
    $question2->multi = '';
    $question2->intro = '';
    $question2->type = 1;
    $question2->options = 'satisfaction_scale5';
    $DB->insert_record('survey_questions', $question2);

    // PREGUNTA 3: Utilidad del curso (1-5)
    $question3 = new stdClass();
    $question3->text = 'satisfaction_q3'; // "¿Qué tan útil fue el curso para ti?"
    $question3->shorttext = 'satisfaction_q3_short';
    $question3->multi = '';
    $question3->intro = '';
    $question3->type = 1;
    $question3->options = 'satisfaction_scale5';
    $DB->insert_record('survey_questions', $question3);

    // PREGUNTA 4: Ritmo del curso (1-5)
    $question4 = new stdClass();
    $question4->text = 'satisfaction_q4'; // "¿El ritmo del curso fue apropiado?"
    $question4->shorttext = 'satisfaction_q4_short';
    $question4->multi = '';
    $question4->intro = '';
    $question4->type = 1;
    $question4->options = 'satisfaction_scale5';
    $DB->insert_record('survey_questions', $question4);

    // PREGUNTA 5: Comentarios abiertos (texto libre)
    $question5 = new stdClass();
    $question5->text = 'satisfaction_q5'; // "¿Qué mejorarías del curso?"
    $question5->shorttext = 'satisfaction_q5_short';
    $question5->multi = '';
    $question5->intro = '';
    $question5->type = 0; // Tipo 0 = texto libre
    $question5->options = ''; // No tiene opciones (es texto abierto)
    $DB->insert_record('survey_questions', $question5);
}

/**
 * RESUMEN DE TIPOS DE PREGUNTAS:
 * 
 * type = 0: Pregunta abierta (textarea)
 *   - El usuario escribe texto libre
 *   - Se guarda en survey_answers.answer1
 * 
 * type = 1: Selección única (radio buttons)
 *   - El usuario elige una opción
 *   - Las opciones vienen de 'options' (ej: "1,2,3,4,5")
 *   - Se guarda el número elegido en survey_answers.answer1
 * 
 * type = 2: Preferido (usado en COLLES, no lo necesitamos)
 * type = 3: Actual y Preferido (usado en COLLES, no lo necesitamos)
 */