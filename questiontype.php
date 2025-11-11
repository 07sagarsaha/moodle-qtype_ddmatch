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
 * Question type class for the drag&drop matching question type.
 *
 * @package    qtype_ddmatch
 *
 * @author DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
/**
 * The drag&drop matching question type class.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddmatch extends question_type {
    /**
     * Get question options from the database.
     *
     * @param stdClass $question The question object.
     * @return bool True on success.
     */
    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);
        $question->options = $DB->get_record(
            'qtype_ddmatch_options',
            ['questionid' => $question->id]
        );

        // If options don't exist, create a default options object.
        if ($question->options === false) {
            $question->options = new stdClass();
            $question->options->questionid = $question->id;
            $question->options->shuffleanswers = 1;
            $question->options->correctfeedback = '';
            $question->options->correctfeedbackformat = FORMAT_HTML;
            $question->options->partiallycorrectfeedback = '';
            $question->options->partiallycorrectfeedbackformat = FORMAT_HTML;
            $question->options->incorrectfeedback = '';
            $question->options->incorrectfeedbackformat = FORMAT_HTML;
            $question->options->shownumcorrect = 0;
        }

        $question->options->subquestions = $DB->get_record(
            'qtype_ddmatch_options',
            ['questionid' => $question->id],
            'id ASC'
        );

        // Ensure subquestions is always an array, even if empty.
        if ($question->options->subquestions === false) {
            $question->options->subquestions = [];
        }

        return true;
    }

    /**
     * Saves the question options into the database.
     *
     * @param stdClass $question The question object.
     * @return bool|stdClass True on success or an object with notices.
     */
    public function save_question_options($question) {
        global $DB;

        $context = $question->context;
        $result = new stdClass();
        $oldsubquestions = $DB->get_records(
            'qtype_ddmatch_subquestions',
            ['questionid' => $question->id],
            'id ASC'
        );
        // Insert all the new question & answer pairs.
        foreach ($question->subquestions as $key => $questiontext) {
            if ($questiontext['text'] == '' && trim($question->subanswers[$key]['text']) == '') {
                continue;
            }
            if ($questiontext['text'] != '' && trim($question->subanswers[$key]['text']) == '') {
                $result->notice = get_string('nomatchinganswer', 'qtype_match', $questiontext['text']);
            }
            // Update an existing subquestion if possible.
            $subquestion = array_shift($oldsubquestions);
            if (!$subquestion) {
                $subquestion = new stdClass();
                $subquestion->questionid = $question->id;
                $subquestion->questiontext = '';
                $subquestion->answertext = '';
                $subquestion->id = $DB->insert_record('qtype_ddmatch_subquestions', $subquestion);
            }
            $subquestion->questiontext = $this->import_or_save_files(
                $questiontext,
                $context,
                'qtype_ddmatch',
                'subquestion',
                $subquestion->id
            );
            $subquestion->questiontextformat = $questiontext['format'];
            $subquestion->answertext = $this->import_or_save_files(
                $question->subanswers[$key],
                $context,
                'qtype_ddmatch',
                'subanswer',
                $subquestion->id
            );
            $subquestion->answertextformat = $question->subanswers[$key]['format'];
            $DB->update_record('qtype_ddmatch_subquestions', $subquestion);
        }
        // Delete old subquestions records.
        $fs = get_file_storage();
        foreach ($oldsubquestions as $oldsub) {
            $fs->delete_area_files($context->id, 'qtype_ddmatch', 'subquestion', $oldsub->id);
            $fs->delete_area_files($context->id, 'qtype_ddmatch', 'subanswer', $oldsub->id);
            $DB->delete_records('qtype_ddmatch_subquestions', ['id' => $oldsub->id]);
        }
        // Save the question options.
        $options = $DB->get_record(
            'qtype_ddmatch_options',
            ['questionid' => $question->id]
        );
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('qtype_ddmatch_options', $options);
        }
        $options->shuffleanswers = $question->shuffleanswers;
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $DB->update_record('qtype_ddmatch_options', $options);
        $this->save_hints($question, true);
        if (!empty($result->notice)) {
            return $result;
        }
        return true;
    }

    /**
     * Initialise a question_definition instance using stored question data.
     *
     * Populates stems, choices, formats, and correct mappings from the DB records.
     * Called automatically when a question instance is being prepared for use.
     *
     * @param question_definition $question The question instance to initialise.
     * @param stdClass $questiondata The raw question data loaded from the database,
     *                               including ->options and ->subquestions.
     * @return void
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->shufflestems = $questiondata->options->shuffleanswers;
        $this->initialise_combined_feedback($question, $questiondata, true);

        $question->stems = [];
        $question->choices = [];
        $question->choiceformat = [];
        $question->right = [];

        // Ensure subquestions exists and is iterable.
        if (!empty($questiondata->options->subquestions) && is_array($questiondata->options->subquestions)) {
            foreach ($questiondata->options->subquestions as $matchsub) {
                $key = array_search($matchsub->answertext, $question->choices);
                if ($key === false) {
                    $key = $matchsub->id;
                    $question->choices[$key] = $matchsub->answertext;
                    // Set the answer text format. Use answertextformat if available, otherwise default to FORMAT_HTML.
                    $question->choiceformat[$key] = isset($matchsub->answertextformat) ? $matchsub->answertextformat : FORMAT_HTML;
                }

                // Only add to stems if questiontext is not empty (allows blank subquestions as distractors).
                if ($matchsub->questiontext !== '') {
                    $question->stems[$matchsub->id] = $matchsub->questiontext;
                    $question->stemformat[$matchsub->id] = $matchsub->questiontextformat;
                    $question->right[$matchsub->id] = $key;
                }
            }
        }
    }

    /**
     * Create a hint object from a database record.
     *
     * @param stdClass $hint The hint data loaded from the database.
     * @return question_hint_with_parts A hint object with parts support.
     */
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    /**
     * Delete all data related to this question type.
     *
     * Removes ddmatch-specific records from the database and then
     * calls the parent method to handle core cleanup.
     *
     * @param int $questionid The ID of the question being deleted.
     * @param int $contextid The context ID where the question files are stored.
     * @return void
     */
    public function delete_question($questionid, $contextid) {
        global $DB;

        $DB->delete_records('qtype_ddmatch_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_ddmatch_subquestions', ['questionid' => $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    /**
     * Calculate the random guess score for the question.
     *
     * Makes a temporary question instance and returns the probability
     * of guessing a correct answer at random.
     *
     * @param object $questiondata The question definition data from the database.
     * @return float Random guess score between 0 and 1.
     */
    public function get_random_guess_score($questiondata) {
        $q = $this->make_question($questiondata);
        return 1 / count($q->choices);
    }

    /**
     * Get the list of possible responses for each stem in the question.
     *
     * Builds a matrix of all possible (stem → choice) responses. Each stem
     * will have a list of `question_possible_response` objects representing
     * each choice the user can select.
     *
     * @param object $questiondata The question definition as loaded from the database.
     * @return array An array indexed by stem id, each containing an array of possible responses.
     */
    public function get_possible_responses($questiondata) {
        $subqs = [];

        $q = $this->make_question($questiondata);

        foreach ($q->stems as $stemid => $stem) {
            $responses = [];
            foreach ($q->choices as $choiceid => $choice) {
                $stemhtml = $q->html_to_text($stem, $q->stemformat[$stemid]);
                // Use choiceformat if available, otherwise default to FORMAT_HTML.
                $choiceformat = isset($q->choiceformat[$choiceid]) ? $q->choiceformat[$choiceid] : FORMAT_HTML;
                $choicehtml = $q->html_to_text($choice, $choiceformat);

                $responses[$choiceid] = new question_possible_response(
                    $stemhtml . ': ' . $choicehtml,
                    ($choiceid == $q->right[$stemid]) / count($q->stems)
                );
            }
            $responses[null] = question_possible_response::no_response();

            $subqs[$stemid] = $responses;
        }

        return $subqs;
    }

    /**
     * Moves all files associated with a question to a new context.
     *
     * This includes files stored in the 'subquestion' and 'subanswer' file areas,
     * as well as files belonging to combined feedback and hints. The operation
     * delegates core file movement to the parent implementation before handling
     * subquestion-specific file areas.
     *
     * @param int $questionid The ID of the question whose files are being moved.
     * @param int $oldcontextid The original context ID.
     * @param int $newcontextid The destination context ID.
     * @return void
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        global $DB;
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);

        $subquestionids = $DB->get_records_menu(
            'qtype_ddmatch_subquestions',
            ['questionid' => $questionid],
            'id',
            'id,1'
        );
        foreach ($subquestionids as $subquestionid => $notused) {
            $fs->move_area_files_to_new_context(
                $oldcontextid,
                $newcontextid,
                'qtype_ddmatch',
                'subquestion',
                $subquestionid
            );
            $fs->move_area_files_to_new_context(
                $oldcontextid,
                $newcontextid,
                'qtype_ddmatch',
                'subanswer',
                $subquestionid
            );
        }

        $this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Deletes all files associated with a question and its subquestions.
     *
     * This includes files stored under 'subquestion' and 'subanswer' file areas,
     * and also delegates deletion of combined feedback and hint files to parent class
     * helper methods.
     *
     * @param int $questionid The ID of the question whose files should be deleted.
     * @param int $contextid The context ID where these files are stored.
     * @return void
     */
    protected function delete_files($questionid, $contextid) {
        global $DB;
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);

        $subquestionids = $DB->get_records_menu(
            'qtype_ddmatch_subquestions',
            ['questionid' => $questionid],
            'id',
            'id,1'
        );
        foreach ($subquestionids as $subquestionid => $notused) {
            $fs->delete_area_files($contextid, 'qtype_ddmatch', 'subquestion', $subquestionid);
            $fs->delete_area_files($contextid, 'qtype_ddmatch', 'subanswer', $subquestionid);
        }

        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    /**
     * Export the question to the given format.
     *
     * @param object $question The question being exported.
     * @param qformat_xml $format The format object being used to export the question.
     * @param object $extra Any additional information required for export.
     * @return string The XML representation of the question.
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $expout = '';
        $fs = get_file_storage();
        $contextid = $question->contextid;
        $expout .= "    <shuffleanswers>" .
                $format->get_single($question->options->shuffleanswers) .
                "</shuffleanswers>\n";
        $expout .= $format->write_combined_feedback(
            $question->options,
            $question->id,
            $question->contextid
        );
        foreach ($question->options->subquestions as $subquestion) {
            $files = $fs->get_area_files($contextid, 'qtype_ddmatch', 'subquestion', $subquestion->id);
            $textformat = $format->get_format($subquestion->questiontextformat);
            $expout .= "    <subquestion format=\"$textformat\">\n";
            $expout .= '      ' . $format->writetext($subquestion->questiontext);
            $expout .= '      ' . $format->write_files($files, 2);
            $expout .= "       <answer format=\"$textformat\">\n";
            $expout .= '      ' . $format->writetext($subquestion->answertext);
            $files = $fs->get_area_files($contextid, 'qtype_ddmatch', 'subanswer', $subquestion->id);
            $expout .= '      ' . $format->write_files($files, 2);
            $expout .= "       </answer>\n";
            $expout .= "    </subquestion>\n";
        }

        return $expout;
    }

    /**
     * Provide import functionality for xml format
     * @param $xml mixed the segment of data containing the question
     * @param $fromform object question object processed (so far) by standard import code
     * @param $format object the format object so that helper methods can be used (in particular error() )
     * @param $extra mixed any additional format specific data that may be passed by the format (see format code for info)
     * @return object question object suitable for save_options() call or false if cannot handle
     */
    public function import_from_xml($xml, $fromform, qformat_xml $format, $extra = null) {
        // Check question is for us.
        $qtype = $xml['@']['type'];
        if ($qtype == 'ddmatch') {
            $fromform = $format->import_headers($xml);

            // Header parts particular to ddmatch qtype.
            $fromform->qtype = $this->name();
            $fromform->shuffleanswers = $format->trans_single(
                $format->getpath(
                    $xml,
                    ['#', 'shuffleanswers', 0, '#'],
                    1
                )
            );

            // Run through subquestions.
            $fromform->subquestions = [];
            $fromform->subanswers = [];
            foreach ($xml['#']['subquestion'] as $subqxml) {
                $fromform->subquestions[] = $format->import_text_with_files(
                    $subqxml,
                    [],
                    '',
                    $format->get_format($fromform->questiontextformat)
                );

                $answers = $format->getpath($subqxml, ['#', 'answer', 0], []);
                $fromform->subanswers[] = $format->import_text_with_files(
                    $answers,
                    [],
                    '',
                    $format->get_format($fromform->questiontextformat)
                );
            }

            $format->import_combined_feedback($fromform, $xml, true);
            $format->import_hints($fromform, $xml, true);
            return $fromform;
        } else {
            return false;
        }
    }
}
