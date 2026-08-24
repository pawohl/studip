<?php
/**
 * @license GPL2 or any later version
 *
 * @property string $id alias column for question_id
 * @property string $question_id database column
 * @property string $questionnaire_id database column
 * @property string $questiontype database column
 * @property string|null $internal_name database column
 * @property JSONArrayObject $questiondata database column
 * @property int $position database column
 * @property int $chdate database column
 * @property int $mkdate database column
 * @property SimpleORMapCollection<QuestionnaireAnswer> $answers has_many QuestionnaireAnswer
 * @property Questionnaire $questionnaire belongs_to Questionnaire
 */
class LikertScale extends QuestionnaireQuestion implements QuestionType
{
    public static function getIcon(bool $active = false) : Icon
    {
        return Icon::create(static::getIconShape(), $active ? 'clickable' : 'info');
    }

    /**
     * Returns the shape of the icon of this QuestionType
     * @return string
     */
    public static function getIconShape()
    {
        return 'question-likert';
    }

    public static function getName()
    {
        return _('Likert-Skala');
    }

    static public function getEditingComponent()
    {
        return ['LikertEdit', ''];
    }

    public function beforeStoringQuestiondata($questiondata)
    {
        $questiondata['description'] = \Studip\Markup::markAsHtml(
            \Studip\Markup::purifyHtml($questiondata['description'])
        );
        $questiondata['statements'] = array_values(
            array_filter($questiondata['statements'], fn($option) => trim($option) !== '')
        );
        return $questiondata;
    }

    public function getDisplayTemplate()
    {
        $factory = new Flexi\Factory(realpath(__DIR__.'/../../app/views'));
        $template = $factory->open('questionnaire/question_types/likert/likert_answer');
        $template->set_attribute('vote', $this);
        return $template;
    }

    public function createAnswer()
    {
        $answer = $this->getMyAnswer();

        $answers = Request::getArray('answers');
        $userAnswer = (array) $answers[$this->getId()]['answerdata']['answers'];
        $userAnswer = array_map(function ($val) { return (int) $val; }, $userAnswer);
        $answer->setData(['answerdata' => ['answers' => $userAnswer ] ]);
        return $answer;
    }

    public function getUserIdsOfFilteredAnswer($answer_option)
    {
        $user_ids = [];
        [$statement_key, $options_key] = explode('_', $answer_option);
        foreach ($this->answers as $answer) {
            $answerData = $answer['answerdata']->getArrayCopy();
            if (
                isset($answerData['answers'][$statement_key])
                && $answerData['answers'][$statement_key] == $options_key
            ) {
                $user_ids[] = $answer['user_id'];
            }
        }
        return $user_ids;
    }

    public function getResultTemplate($only_user_ids = null, $filtered = null)
    {
        $answers = $this->answers;
        if ($only_user_ids !== null) {
            foreach ($answers as $key => $answer) {
                if (!in_array($answer['user_id'], $only_user_ids)) {
                    unset($answers[$key]);
                }
            }
        }
        $factory = new Flexi\Factory(realpath(__DIR__.'/../../app/views'));
        $template = $factory->open('questionnaire/question_types/likert/likert_evaluation');
        $template->set_attribute('vote', $this);
        $template->set_attribute('answers', $answers);
        $template->set_attribute('filtered', $filtered);
        return $template;
    }

    public function getResultArray()
    {
        $output = [];

        $statements = $this['questiondata']['statements']->getArrayCopy();

        foreach ($statements as $statement_key => $statement) {
            $answerOption = [];
            $countNobodys = 0;

            foreach ($this->answers as $answer) {
                $answerData = $answer['answerdata']->getArrayCopy();

                if ($answer['user_id'] && $answer['user_id'] != 'nobody') {
                    $userId = $answer['user_id'];
                } else {
                    $countNobodys++;
                    $userId = _('unbekannt').' '.$countNobodys;
                }

                $answerOption[$userId] = $this['questiondata']['options'][$answerData['answers'][$statement_key]];
            }
            $output[$statement] = $answerOption;
        }
        return $output;
    }
}
