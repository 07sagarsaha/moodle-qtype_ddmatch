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
 * Drag-and-drop matching question type class.
 *
 * @package    qtype_ddmatch
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/match/question.php');

/**
 * Represents a drag&drop matching question.
 * Based on core matching question.
 *
 * @package    qtype_ddmatch
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddmatch_question extends qtype_match_question {
    /** @var array List of question stems. */
    public $stems = [];

    /** @var array Format of each stem. */
    public $stemformat = [];

    /** @var array Order of stems. */
    public $stemorder = [];

    /** @var array List of answer choices. */
    public $choices = [];

    /** @var array Format of each choice. */
    public $choiceformat = [];

    /** @var array Order of answer choices. */
    public $choiceorder = [];

    /** @var array Right answers mapping. */
    public $right = [];

    /** @var int Whether stems are shuffled (1 = true, 0 = false). */
    public $shufflestems = 0;

    /**
     * Returns a plain-text summary of the question.
     *
     * @return string The question summary.
     */
    public function get_question_summary() {
        $question = $this->html_to_text($this->questiontext, $this->questiontextformat);

        $stems = [];
        foreach ($this->stemorder as $stemid) {
            $stems[] = $this->html_to_text($this->stems[$stemid], $this->stemformat[$stemid]);
        }

        $choices = [];
        foreach ($this->choiceorder as $choiceid) {
            $choices[] = $this->choices[$choiceid];
        }

        return $question . ' {' . implode('; ', $stems) . '} -> {' . implode('; ', $choices) . '}';
    }

    /**
     * Summarises the response for reporting or review.
     *
     * @param array $response The user’s response data.
     * @return string|null Summary of the response or null if empty.
     */
    public function summarise_response(array $response) {
        $matches = [];
        foreach ($this->stemorder as $key => $stemid) {
            if (array_key_exists($this->field($key), $response) && $response[$this->field($key)]) {
                $matches[] = $this->html_to_text(
                    $this->stems[$stemid],
                    $this->stemformat[$stemid]
                ) . ' -> ' . $this->choices[$this->choiceorder[$response[$this->field($key)]]];
            }
        }

        if (empty($matches)) {
            return null;
        }

        return implode('; ', $matches);
    }

    /**
     * Controls file access for feedback and subquestion resources.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options Display options.
     * @param string $component The component name.
     * @param string $filearea The file area.
     * @param array $args Additional file arguments.
     * @param bool $forcedownload Force download flag.
     * @return bool True if access is allowed, false otherwise.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component === 'qtype_ddmatch' && $filearea === 'subquestion') {
            $subqid = reset($args);
            return array_key_exists($subqid, $this->stems);
        } else if ($component === 'qtype_ddmatch' && $filearea === 'subanswer') {
            $subqid = reset($args);
            return array_key_exists($subqid, $this->choices);
        } else if (
            $component === 'question'
            && in_array(
                $filearea,
                ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'],
                true
            )
        ) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea);
        } else if ($component === 'question' && $filearea === 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }    

    /**
     * Returns the field name for a given stem key.
     *
     * @param int $key The key index.
     * @return string The field name.
     */
    public function get_field_name($key) {
        return $this->field($key);
    }
}
