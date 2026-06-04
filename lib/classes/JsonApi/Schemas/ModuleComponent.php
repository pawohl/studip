<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class ModuleComponent extends SchemaProvider
{
    const REL_COURSES = 'courses';

    protected ?array $allowedIncludes = [
        self::REL_COURSES,
    ];
    const TYPE = 'module-components';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'name' => (string) $resource->deskriptoren->bezeichnung,
            'position' => $resource->position,
            'foreign_key' => $resource->flexnow_modul,
            'number' => $resource->nummer,
            'number_label' => \Config::get()->MVV_MODULTEIL['NUM_BEZEICHNUNG']['values'][$resource->num_bezeichnung] ?? '',
            'teaching_method' => \Config::get()->MVV_MODULTEIL['LERNLEHRFORM']['values'][$resource->lernlehrform] ?? '',
            'semester' => $resource->semester,
            'number_of_participants' => $resource->kapazitaet,
            'cp' => $resource->kp,
            'sws' => $resource->sws,
            'workload_compulsory' => $resource->wl_praesenz,
            'workload_preparation' => $resource->wl_bereitung,
            'workload_self' => $resource->wl_selbst,
            'workload_exam' => $resource->wl_pruef,
            'share_of_grade' => $resource->anteil_note,
            'compensable' => $resource->ausgleichbar,
            'compulsory_attendance' => $resource->pflicht,
            'prerequisites' => $resource->deskriptoren->voraussetzung,
            'comment' => $resource->deskriptoren->kommentar,
            'comment_capacity' => $resource->deskriptoren->kommentar_kapazitaet,
            'comment_wl_compulsory' => $resource->deskriptoren->kommentar_wl_praesenz,
            'comment_wl_preparation' => $resource->deskriptoren->kommentar_wl_bereitung,
            'comment_wl_self' => $resource->deskriptoren->kommentar_wl_selbst,
            'comment_wl_exam' => $resource->deskriptoren->kommentar_wl_pruef,
            'exam_prerequisites' => $resource->deskriptoren->pruef_vorleistung,
            'exam_requirements' => $resource->deskriptoren->pruef_leistung,
            'comment_compulsory_attendance' => $resource->deskriptoren->kommentar_pflicht,
            'type' => get_class($resource)
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addCoursesRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_COURSES));

        return $relationships;
    }

    private function addCoursesRelationship(array $relationships, \Modulteil $resource, $includeData)
    {
        $relationships[self::REL_COURSES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_COURSES),
            ],
        ];

        return $relationships;
    }
}
