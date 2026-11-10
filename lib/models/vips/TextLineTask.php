<?php
/*
 * TextLineTask.php - Vips plugin for Stud.IP
 * Copyright (c) 2006-2011  Elmar Ludwig, Martin Schröder
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

/**
 * @license GPL2 or any later version
 *
 * @property int $id database column
 * @property string $type database column
 * @property string $title database column
 * @property string $description database column
 * @property string $task database column
 * @property string $user_id database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property JSONArrayObject $options database column
 * @property SimpleORMapCollection<VipsExerciseRef> $exercise_refs has_many VipsExerciseRef
 * @property SimpleORMapCollection<VipsSolution> $solutions has_many VipsSolution
 * @property User $user belongs_to User
 * @property Folder $folder has_one Folder
 * @property SimpleORMapCollection<VipsTest> $tests has_and_belongs_to_many VipsTest
 */
class TextLineTask extends Exercise
{
    /**
     * Get the icon of this exercise type.
     */
    public static function getTypeIcon(string $role = Icon::DEFAULT_ROLE): Icon
    {
        return Icon::create('task-text-line', $role);
    }

    /**
     * Get a description of this exercise type.
     */
    public static function getTypeDescription(): string
    {
        return _('Kurze einzeilige Textantwort');
    }

    /**
     * Initialize this instance from the current request environment.
     */
    public function initFromRequest($request): void
    {
        parent::initFromRequest($request);

        foreach ($request['answer'] as $i => $answer) {
            if (trim($answer) != '') {
                $this->task['answers'][] = [
                    'text'  => trim($answer),
                    'score' => (float) $request['correct'][$i]
                ];
            }
        }

        $this->task['compare'] = $request['compare'];

        if ($this->task['compare'] === 'numeric') {
            $this->task['epsilon'] = (float) strtr($request['epsilon'], ',', '.') / 100;
        }
    }

    /**
     * Evaluates a student's solution for the individual items in this
     * exercise. Returns an array of ('points' => float, 'safe' => boolean).
     *
     * @param mixed $solution The solution XML string as returned by responseFromRequest().
     */
    public function evaluateItems($solution): array
    {
        $result = [];

        $response = $solution->response;
        $studentSolution = $response[0] ?? '';

        $similarity = 0;
        $safe = false;
        $studentSolution = $this->normalizeText($studentSolution, true);

        if ($studentSolution === '') {
            $result[] = ['points' => 0, 'safe' => true];
            return $result;
        }

        foreach ($this->task['answers'] as $answer) {
            $musterLoesung = $this->normalizeText($answer['text'], true);
            $similarity_temp = 0;

            if ($musterLoesung === $studentSolution) {
                $similarity_temp = 1;
            } else if ($this->task['compare'] === 'levenshtein') {  // Levenshtein-Distanz
                $string1 = mb_substr($studentSolution, 0, 255);
                $string2 = mb_substr($musterLoesung, 0, 255);
                $divisor = max(mb_strlen($string1), mb_strlen($string2));

                $levenshtein = $this->levenshtein($string1, $string2) / $divisor;
                $similarity_temp = 1 - $levenshtein;
            } else if ($this->task['compare'] === 'soundex') {  // Soundex-Aussprache
                $levenshtein = levenshtein(soundex($musterLoesung), soundex($studentSolution));

                if ($levenshtein == 0) {
                    $similarity_temp = 0.8;
                } else if ($levenshtein == 1) {
                    $similarity_temp = 0.6;
                } else if ($levenshtein == 2) {
                    $similarity_temp = 0.4;
                } else if ($levenshtein == 3) {
                    $similarity_temp = 0.2;
                } else {// $levenshtein == 4
                    $similarity_temp = 0;
                }
            } else if ($this->task['compare'] === 'numeric') {
                $correct = $this->normalizeFloat($answer['text'], $correct_unit);
                $student = $this->normalizeFloat($response[0], $student_unit);

                if ($correct_unit === $student_unit) {
                    if (abs($correct - $student) <= abs($correct * $this->task['epsilon'])) {
                        $similarity_temp = 1;
                    } else {
                        $safe = true;
                    }
                }
            }

            if ($answer['score'] == 1) {  // correct
                if ($similarity_temp > $similarity) {
                    $similarity = $similarity_temp;
                    $safe = $similarity_temp == 1;
                }
            } else if ($answer['score'] == 0.5) {  // half correct
                if ($similarity_temp > $similarity) {
                    $similarity = $similarity_temp * 0.5;
                    $safe = $similarity_temp == 1;
                }
            } else if ($similarity_temp == 1) {  // false
                $similarity = 0;
                $safe = true;
                break;
            }
        }

        $result[] = ['points' => $similarity, 'safe' => $safe];

        return $result;
    }

    /**
     * Return the list of keywords used for text export. The first keyword
     * in the list must be the keyword for the exercise type.
     */
    public static function getTextKeywords(): array
    {
        return ['Frage', 'Eingabehilfe', 'Abgleich', '[+~]?Antwort'];
    }

    /**
     * Initialize this instance from the given text data array.
     */
    public function initText(array $exercise): void
    {
        parent::initText($exercise);

        foreach ($exercise as $tag) {
            if (key($tag) === 'Abgleich') {
                if (current($tag) === 'Levenshtein') {
                    $this->task['compare'] = 'levenshtein';
                } else if (current($tag) === 'Soundex') {
                    $this->task['compare'] = 'soundex';
                }
            }

            if (key($tag) === '+Antwort') {
                $this->task['answers'][] = [
                    'text'  => current($tag),
                    'score' => 1
                ];
            } else if (key($tag) === '~Antwort') {
                $this->task['answers'][] = [
                    'text'  => current($tag),
                    'score' => 0.5
                ];
            } else if (key($tag) === 'Antwort') {
                $this->task['answers'][] = [
                    'text'  => current($tag),
                    'score' => 0
                ];
            }
        }
    }

    /**
     * Initialize this instance from the given SimpleXMLElement object.
     */
    public function initXML($exercise): void
    {
        parent::initXML($exercise);

        foreach ($exercise->items->item->answers->answer as $answer) {
            $this->task['answers'][] = [
                'text'  => trim($answer),
                'score' => (float) $answer['score']
            ];
        }

        if ($exercise->items->item->{'evaluation-hints'}) {
            switch ($exercise->items->item->{'evaluation-hints'}->similarity['type']) {
                case 'levenshtein':
                case 'soundex':
                    $this->task['compare'] = (string) $exercise->items->item->{'evaluation-hints'}->similarity['type'];
                    break;
                case 'numeric':
                    $this->task['compare'] = 'numeric';
                    $this->task['epsilon'] = (float) $exercise->items->item->{'evaluation-hints'}->{'input-data'};
            }
        }
    }

    /**
     * Creates a template for editing a TextLineTask.
     */
    public function getEditTemplate(?VipsAssignment $assignment): Flexi\Template
    {
        if (!$this->task['answers']) {
            $this->task['answers'] = array_fill(0, 5, ['text' => '', 'score' => 0]);
        }

        return parent::getEditTemplate($assignment);
    }

    /**
     * Create a template for viewing an exercise.
     */
    public function getViewTemplate(
        string $view,
        ?VipsSolution $solution,
        VipsAssignment $assignment,
        ?string $user_id
    ): Flexi\Template {
        $template = parent::getViewTemplate($view, $solution, $assignment, $user_id);

        if ($solution && $solution->id) {
            $template->results = $this->evaluateItems($solution);
        }

        return $template;
    }

    /**
     * Returns all the correct answers in an array.
     */
    public function correctAnswers(): array
    {
        $answers = [];

        foreach ($this->task['answers'] as $answer) {
            if ($answer['score'] == 1) {
                $answers[] = $answer['text'];
            }
        }

        return $answers;
    }
}
