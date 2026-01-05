<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHA-NTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'coursesat', language 'en'
 *
 * @package    mod_coursesat
 * @copyright  2024 Tu Nombre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// ===============================================
// NOMBRE DEL MÓDULO (LO QUE VE EL USUARIO)
// ===============================================
$string['modulename'] = 'Encuesta de Satisfacción';
$string['modulenameplural'] = 'Encuestas de Satisfacción';
$string['modulename_help'] = 'La actividad de Encuesta de Satisfacción permite recopilar feedback de los estudiantes sobre el curso.';
$string['modulename_link'] = 'mod/coursesat/view';
$string['pluginname'] = 'Encuesta de Satisfacción';
$string['pluginadministration'] = 'Administración de Encuesta de Satisfacción';

// ===============================================
// STRINGS BÁSICOS
// ===============================================
$string['name'] = 'Nombre';
$string['completionsubmit'] = 'Enviar respuestas';
$string['completiondetail:submit'] = 'Enviar respuestas';
$string['alreadysubmitted'] = 'Ya has enviado esta encuesta';
$string['questionsnotanswered'] = 'Algunas preguntas no han sido respondidas.';
$string['allquestionrequireanswer'] = 'Todas las preguntas son obligatorias y deben ser respondidas.';

// ===============================================
// TEMPLATE DE SATISFACCIÓN
// ===============================================
$string['satisfactionname'] = 'Encuesta de Satisfacción del Curso';
$string['satisfactionintro'] = 'Por favor, ayúdanos a mejorar respondiendo esta breve encuesta sobre tu experiencia en el curso.';

// ===============================================
// LAS 5 PREGUNTAS
// ===============================================

// Pregunta 1: Calificación general
$string['satisfaction_q1'] = '¿Cómo calificarías el curso en general?';
$string['satisfaction_q1_short'] = 'Calificación general';

// Pregunta 2: Claridad
$string['satisfaction_q2'] = '¿El contenido del curso fue claro y fácil de entender?';
$string['satisfaction_q2_short'] = 'Claridad del contenido';

// Pregunta 3: Utilidad
$string['satisfaction_q3'] = '¿Qué tan útil fue este curso para alcanzar tus objetivos de aprendizaje?';
$string['satisfaction_q3_short'] = 'Utilidad del curso';

// Pregunta 4: Ritmo
$string['satisfaction_q4'] = '¿El ritmo del curso fue apropiado?';
$string['satisfaction_q4_short'] = 'Ritmo del curso';

// Pregunta 5: Comentarios
$string['satisfaction_q5'] = '¿Qué aspectos del curso crees que deberían mejorarse? (opcional)';
$string['satisfaction_q5_short'] = 'Comentarios y sugerencias';

// ===============================================
// ESCALA 1-5
// ===============================================
$string['satisfaction_scale5'] = 'Muy malo,Malo,Regular,Bueno,Excelente';

// ===============================================
// CAPACIDADES
// ===============================================
$string['coursesat:addinstance'] = 'Agregar nueva encuesta de satisfacción';
$string['coursesat:participate'] = 'Responder encuesta';
$string['coursesat:readresponses'] = 'Ver respuestas';
$string['coursesat:download'] = 'Descargar respuestas';

// ===============================================
// EVENTOS
// ===============================================
$string['eventreportdownloaded'] = 'Reporte de encuesta descargado';
$string['eventreportviewed'] = 'Reporte de encuesta visto';
$string['eventresponsesubmitted'] = 'Respuesta de encuesta enviada';

// ===============================================
// ERRORES
// ===============================================
$string['cannotfindquestion'] = 'No se puede encontrar la pregunta';
$string['cannotfindcoursesattmpt'] = '¡No se encontraron plantillas de encuesta!';
$string['invalidcoursesatid'] = 'ID de encuesta inválido';
$string['invalidtmptid'] = 'ID de plantilla inválido';

// ===============================================
// PRIVACY
// ===============================================
$string['privacy:metadata:answers'] = 'Colección de respuestas a encuestas.';
$string['privacy:metadata:answers:answer1'] = 'Campo para almacenar la respuesta a una pregunta.';
$string['privacy:metadata:answers:answer2'] = 'Campo adicional para almacenar la respuesta a una pregunta.';
$string['privacy:metadata:answers:question'] = 'La pregunta.';
$string['privacy:metadata:answers:time'] = 'El momento en que se publicó la respuesta.';
$string['privacy:metadata:answers:userid'] = 'El ID del usuario que envió su respuesta.';
$string['privacy:metadata:analysis'] = 'Un registro de análisis de respuestas de encuestas.';
$string['privacy:metadata:analysis:notes'] = 'Notas guardadas contra las respuestas de un usuario.';
$string['privacy:metadata:analysis:userid'] = 'El ID del usuario que responde la encuesta.';