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
 * Upgrade library code for the match question type.
 *
 * @package    qtype_ddmatch
 *
 * @author DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class for converting attempt data for match questions when upgrading
 * attempts to the new question engine.
 *
 * This class is used by the code in question/engine/upgrade/upgradelib.php.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddmatch_qe2_attempt_updater extends question_qtype_attempt_updater {
    /**
     * The list of stem texts for the question.
     * @var array
     */
    protected $stems;

    /**
     * The list of possible choices for matching.
     * @var array
     */
    protected $choices;

    /**
     * Mapping of correct answers (stem ID → choice ID).
     * @var array
     */
    protected $right;

    /**
     * Order of stems presented to the student.
     * @var array
     */
    protected $stemorder;

    /**
     * Order of choices presented to the student.
     * @var array
     */
    protected $choiceorder;

    /**
     * Flipped mapping of choice order for quick lookup.
     * @var array
     */
    protected $flippedchoiceorder;

    /**
     * Returns a text summary of the question including stems and choices.
     *
     * @return string Summary of the question text.
     */
    public function question_summary() {
        $this->stems = [];
        $this->choices = [];
        $this->right = [];

        foreach ($this->question->options->subquestions as $matchsub) {
            $ans = $matchsub->answertext;
            $key = array_search($matchsub->answertext, $this->choices);
            if ($key === false) {
                $key = $matchsub->id;
                $this->choices[$key] = $matchsub->answertext;
            }

            if ($matchsub->questiontext !== '') {
                $this->stems[$matchsub->id] = $this->to_text($matchsub->questiontext);
                $this->right[$matchsub->id] = $key;
            }
        }

        return $this->to_text($this->question->questiontext) . ' {' .
                implode('; ', $this->stems) . '} -> {' . implode('; ', $this->choices) . '}';
    }
    /**
     * Returns the correctly matched answer summary for the question.
     *
     * @return string The correct answer mapping (stem → choice).
     */
    public function right_answer() {
        $answer = [];
        foreach ($this->stems as $key => $stem) {
            $answer[$stem] = $this->choices[$this->right[$key]];
        }
        return $this->make_summary($answer);
    }

    /**
     * Breaks the stored answer string into an associative array.
     *
     * @param string $answer The raw answer string from attempt data.
     * @return array An array mapping stem IDs to selected choice IDs.
     */
    protected function explode_answer($answer) {
        if (!$answer) {
            return [];
        }
        $bits = explode(',', $answer);
        $selections = [];
        foreach ($bits as $bit) {
            [$stem, $choice] = explode('-', $bit);
            $selections[$stem] = $choice;
        }
        return $selections;
    }

    /**
     * Builds a summary string from stem-choice pairs.
     *
     * @param array $pairs Array of stem → answer text.
     * @return string Formatted summary.
     */
    protected function make_summary($pairs) {
        $bits = [];
        foreach ($pairs as $stem => $answer) {
            $bits[] = $stem . ' -> ' . $answer;
        }
        return implode('; ', $bits);
    }

    /**
     * Finds the internal choice ID for a given choice code.
     *
     * @param string|int $choice The choice code to look up.
     * @return int|null The matching choice ID, or null if not found.
     */
    protected function lookup_choice($choice) {
        foreach ($this->question->options->subquestions as $matchsub) {
            if ($matchsub->code == $choice) {
                if (array_key_exists($matchsub->id, $this->choices)) {
                    return $matchsub->id;
                } else {
                    return array_search($matchsub->answertext, $this->choices);
                }
            }
        }
        return null;
    }

    /**
     * Builds a summary of a student's response for reporting or review.
     *
     * @param object $state The attempt state object.
     * @return string|null Human-readable summary of the response.
     */
    public function response_summary($state) {
        $choices = $this->explode_answer($state->answer);
        if (empty($choices)) {
            return null;
        }

        $pairs = [];
        foreach ($choices as $stemid => $choicekey) {
            if (array_key_exists($stemid, $this->stems) && $choices[$stemid]) {
                $choiceid = $this->lookup_choice($choicekey);
                if ($choiceid) {
                    $pairs[$this->stems[$stemid]] = $this->choices[$choiceid];
                } else {
                    $this->logger->log_assumption("Dealing with a place where the
                            student selected a choice that was later deleted for
                            match question {$this->question->id}");
                    $pairs[$this->stems[$stemid]] = '[CHOICE THAT WAS LATER DELETED]';
                }
            }
        }

        if ($pairs) {
            return $this->make_summary($pairs);
        } else {
            return '';
        }
    }

    /**
     * Checks whether the student has provided any response.
     *
     * @param object $state The current attempt state.
     * @return bool True if any choice was selected, false otherwise.
     */
    public function was_answered($state) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $choice) {
            if ($choice) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prepares and stores stem and choice order data for the first attempt step.
     *
     * @param object $state The current question state.
     * @param array  $data  Reference to the step data array to populate.
     * @return void
     */
    public function set_first_step_data_elements($state, &$data) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $key => $notused) {
            if (array_key_exists($key, $this->stems)) {
                $this->stemorder[] = $key;
            }
        }

        $this->choiceorder = array_keys($this->choices);
        shuffle($this->choiceorder);
        $this->flippedchoiceorder = array_combine(
            array_values($this->choiceorder),
            array_keys($this->choiceorder)
        );

        $data['_stemorder'] = implode(',', $this->stemorder);
        $data['_choiceorder'] = implode(',', $this->choiceorder);
    }

    /**
     * Supplies missing data for the first question attempt step.
     *
     * This method is expected to populate default values for stem and choice order
     * if they are not already provided in the first step of the question attempt.
     * Currently, this function throws a coding exception indicating it has not been tested.
     *
     * @param array $data The step data array, passed by reference.
     * @return void
     * @throws coding_exception If the method is called (not yet implemented/tested).
     */
    public function supply_missing_first_step_data(&$data) {
        throw new coding_exception('qtype_ddmatch_updater::supply_missing_first_step_data ' .
                'not tested');
        $data['_stemorder'] = array_keys($this->stems);
        $data['_choiceorder'] = shuffle(array_keys($this->choices));
    }

    /**
     * Sets the response data elements for a given question attempt step.
     *
     * This function prepares the response data for each stem in the question based on
     * the student's selected choices. It maps each stem to the corresponding choice
     * index within the flipped choice order array.
     *
     * @param object $state The current question attempt state containing the student's answer.
     * @param array $data   The data array that will be populated with subquestion responses by reference.
     * @return void
     */
    public function set_data_elements_for_step($state, &$data) {
        $choices = $this->explode_answer($state->answer);

        foreach ($this->stemorder as $i => $key) {
            if (empty($choices[$key])) {
                $data['sub' . $i] = 0;
                continue;
            }
            $choice = $this->lookup_choice($choices[$key]);

            if (array_key_exists($choice, $this->flippedchoiceorder)) {
                $data['sub' . $i] = $this->flippedchoiceorder[$choice] + 1;
            } else {
                $data['sub' . $i] = 0;
            }
        }
    }
}
