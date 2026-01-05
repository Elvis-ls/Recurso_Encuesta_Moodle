<?php
/**
 * ARCHIVO: mod_form.php
 * 
 * PROPÓSITO: Formulario de configuración que ve el profesor
 * cuando crea/edita la actividad
 * 
 * MODIFICACIÓN: Simplificamos para usar solo la encuesta de satisfacción
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_coursesat_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;

        // =============================================
        // SECCIÓN 1: INFORMACIÓN BÁSICA
        // =============================================
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Nombre de la actividad
        // Esto es lo que verán los estudiantes en el curso
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        // =============================================
        // TEMPLATE AUTOMÁTICO
        // =============================================
        // En lugar de dejar que el profesor elija, 
        // automáticamente usamos la encuesta de satisfacción
        
        // Buscamos el template de satisfacción
        $satisfactiontemplate = $DB->get_record('coursesat', 
            array('name' => 'satisfactionname', 'template' => 0));
        
        if (!$satisfactiontemplate) {
            throw new \moodle_exception('cannotfindcoursesattmpt', 'coursesat', '', 
                'No se encontró el template de satisfacción. ¿Ejecutaste la instalación?');
        }

        // Campo oculto con el template (el profesor no lo ve)
        $mform->addElement('hidden', 'template', $satisfactiontemplate->id);
        $mform->setType('template', PARAM_INT);

        // Mensaje informativo para el profesor
        $mform->addElement('static', 'templateinfo', 
            get_string('coursesattype', 'coursesat'),
            '<strong>Encuesta de Satisfacción del Curso</strong><br>' .
            'Esta encuesta contiene 5 preguntas diseñadas para medir la satisfacción de los estudiantes.');

        // =============================================
        // DESCRIPCIÓN (OPCIONAL)
        // =============================================
        // El profesor puede agregar instrucciones adicionales
        $this->standard_intro_elements(get_string('customintro', 'coursesat'));

        // =============================================
        // CONFIGURACIÓN ESTÁNDAR DE MOODLE
        // =============================================
        // Esto agrega automáticamente:
        // - Configuración de finalización
        // - Restricciones de acceso
        // - Grupos
        // - etc.
        $this->standard_coursemodule_elements();

        // Botones de guardar/cancelar
        $this->add_action_buttons();
    }

    /**
     * Procesamiento de datos después de enviar el formulario
     * Aquí manejamos la configuración de finalización
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            $completion = $data->{'completion' . $suffix};
            $autocompletion = !empty($completion) && $completion == COMPLETION_TRACKING_AUTOMATIC;
            if (!$autocompletion || empty($data->{'completionsubmit' . $suffix})) {
                $data->{'completionsubmit' . $suffix} = 0;
            }
        }
    }

    /**
     * Reglas de finalización
     * Permitimos que el profesor configure que la actividad
     * se marque como completa cuando el estudiante responda
     */
    public function add_completion_rules() {
        $mform =& $this->_form;
        $suffix = $this->get_suffix();
        $completionsubmitel = 'completionsubmit' . $suffix;
        
        $mform->addElement('checkbox', $completionsubmitel, '', 
            get_string('completionsubmit', 'coursesat'));
        // Por defecto, marcamos la casilla
        $mform->setDefault($completionsubmitel, 1);
        
        return [$completionsubmitel];
    }

    /**
     * Verifica si las reglas de finalización están habilitadas
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionsubmit' . $suffix]);
    }
}

/**
 * RESUMEN DE CAMBIOS EN ESTE ARCHIVO:
 * 
 * ANTES (versión original):
 * - El profesor elegía entre ATTLS, COLLES, CIQ
 * - Dropdown con múltiples opciones
 * - Confuso para un uso simple
 * 
 * DESPUÉS (versión modificada):
 * - Template automático de satisfacción
 * - Campo oculto (el profesor no elige)
 * - Mensaje informativo sobre qué preguntas contiene
 * - Más simple y directo
 * 
 * VENTAJAS:
 * 1. El profesor solo pone nombre y descripción
 * 2. No hay confusión sobre qué tipo de encuesta usar
 * 3. Siempre usa las mismas 5 preguntas de satisfacción
 * 4. Más fácil de entender para profesores no técnicos
 */