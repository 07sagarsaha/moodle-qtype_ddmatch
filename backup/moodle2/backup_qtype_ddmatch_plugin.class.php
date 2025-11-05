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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Backup handler for the Drag-and-Drop Matching question type.
 *
 * Defines how ddmatch question data is exported during course backups.
 *
 * @package   qtype_ddmatch
 * @category  backup
 * @copyright 2007 DualCube
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to back up ddmatch questions.
 *
 * @copyright 2010 onwards Eloy Lafuente (stronk7)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_ddmatch_plugin extends backup_qtype_plugin {
    /**
     * Defines the structure for the ddmatch question plugin during backup.
     *
     * @return backup_nested_element The plugin element for backup.
     */
    protected function define_question_plugin_structure() {
        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'ddmatch');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Define the qtype-specific structures.
        $matchoptions = new backup_nested_element(
            'matchoptions',
            ['id'],
            [
                'shuffleanswers',
                'correctfeedback',
                'correctfeedbackformat',
                'partiallycorrectfeedback',
                'partiallycorrectfeedbackformat',
                'incorrectfeedback',
                'incorrectfeedbackformat',
                'shownumcorrect',
            ]
        );

        $matches = new backup_nested_element('matches');

        $match = new backup_nested_element(
            'match',
            ['id'],
            [
                'questiontext',
                'questiontextformat',
                'answertext',
                'answertextformat',
            ]
        );

        // Build the tree.
        $pluginwrapper->add_child($matchoptions);
        $pluginwrapper->add_child($matches);
        $matches->add_child($match);

        // Set sources.
        $matchoptions->set_source_table(
            'qtype_ddmatch_options',
            ['questionid' => backup::VAR_PARENTID]
        );

        $match->set_source_table(
            'qtype_ddmatch_subquestions',
            ['questionid' => backup::VAR_PARENTID],
            'id ASC'
        );

        // No ID annotations or file annotations needed.
        return $plugin;
    }

    /**
     * Returns an array mapping fileareas to mapping names for this qtype.
     *
     * Used by {@see get_components_and_fileareas()} to locate files for
     * backup and restore operations.
     *
     * @return array<string, string> File areas and their mapping names.
     */
    public static function get_qtype_fileareas() {
        return [
            'correctfeedback' => 'question_created',
            'partiallycorrectfeedback' => 'question_created',
            'incorrectfeedback' => 'question_created',
            'subquestion' => 'qtype_ddmatch_subquestions',
            'subanswer' => 'qtype_ddmatch_subquestions',
        ];
    }
}
