<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Module extends SchemaProvider
{
    const REL_DEPARTMENTS = 'departments';
    const REL_RESPONSIBLE_DEPARTMENT = 'responsible-department';
    const REL_SOURCE_MODULE = 'source-module';
    const REL_VARIANT_MODULE = 'variant-module';
    const REL_START_SEMESTER = 'start-semester';
    const REL_END_SEMESTER = 'end-semester';
    const REL_MODULE_COMPONENTS = 'module-components';
    const REL_LANGUAGES = 'languages';

    protected ?array $allowedIncludes = [
        self::REL_DEPARTMENTS,
        self::REL_RESPONSIBLE_DEPARTMENT,
        self::REL_SOURCE_MODULE,
        self::REL_VARIANT_MODULE,
        self::REL_START_SEMESTER,
        self::REL_END_SEMESTER,
        self::REL_MODULE_COMPONENTS,
        self::REL_LANGUAGES,
    ];

    const TYPE = 'modules';

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'display-name' => (string) $resource->getDisplayName(),
            'code' => (string) $resource->code,
            'date-of-decision' => $resource->beschlussdatum ? date('c', $resource->beschlussdatum) : '',
            'edition-number' => (string) $resource->fassung_nr,
            'edition-type' => \Config::get()->MVV_STGTEILVERSION['FASSUNG_TYP'][$resource->fassung_typ] ?? '',
            'version-number' => (string) $resource->version,
            'semester-duration' => (string) $resource->dauer,
            'capacity' => (string) $resource->kapazitaet,
            'cp' => $resource->kp,
            'workload-self' => (string) $resource->wl_selbst,
            'workload-exam' => (string) $resource->wl_pruef,
            'examination-period' => \Config::get()->MVV_MODUL['PRUEF_EBENE']['values'][$resource->pruef_ebene] ?? '',
            'grade-factor' => (string) $resource->faktor_note,
        //    'module-responsible' => (string) $resource->verantwortlich,
            'foreign-key' => (string) $resource->flexnow_modul,
            'name' => (string) $resource->deskriptoren->bezeichnung,
            'responsible' => (string) $resource->deskriptoren->verantwortlich,
            'prerequisite' => (string) $resource->deskriptoren->voraussetzung,
            'objectives' => (string) $resource->deskriptoren->kompetenzziele,
            'content' => (string) $resource->deskriptoren->inhalte,
            'literature' => (string) $resource->deskriptoren->literatur,
            'links' => (string) $resource->deskriptoren->links,
            'comment' => (string) $resource->deskriptoren->kommentar,
            'cycle' => (string) $resource->deskriptoren->turnus,
            'comment-capacity' => (string) $resource->deskriptoren->kommentar_kapazitaet,
            'comment_sws' => (string) $resource->deskriptoren->kommentar_sws,
            'type' => get_class($resource),
            'stat' => $resource->stat,
            'status' => \Config::get()->MVV_MODUL['STATUS']['values'][$resource->stat],
        ];
    }

    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        if ($semester = $this->getStartSemester($resource)) {
            $relationships[self::REL_START_SEMESTER] = $semester;
        }
        if ($semester = $this->getEndSemester($resource)) {
            $relationships[self::REL_END_SEMESTER] = $semester;
        }
        if ($responsible_department = $this->getResponsibleDepartment($resource)) {
            $relationships[self::REL_RESPONSIBLE_DEPARTMENT] = $responsible_department;
        }
        /*
        if (!empty($resource->responsible_institute)) {
            $relationships[self::REL_RESPONSIBLE_DEPARTMENT] =
                 [
                     self::RELATIONSHIP_LINKS => [
                         Link::RELATED => $this->createLinkToResource($resource->responsible_institute->institute),
                     ],
                     self::RELATIONSHIP_DATA => $resource->responsible_institute,
                 ];
        }
        */
/*
        $relationships = $this->addResponsibleDepartmentRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_RESPONSIBLE_DEPARTMENT)
        );
*/
        $relationships = $this->addDepartments(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_DEPARTMENTS)
        );
        $relationships = $this->addModuleComponentsRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_MODULE_COMPONENTS)
        );

        return $relationships;
    }

    private function getStartSemester(\Modul $modul)
    {
        if (!$semester = \Semester::find($modul->start)) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($semester),
            ],
            self::RELATIONSHIP_DATA => $semester,
        ];
    }

    private function getEndSemester(\Modul $modul)
    {
        $semester = \Semester::find($modul->end);
        if (!$semester) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($semester),
            ],
            self::RELATIONSHIP_DATA => $semester,
        ];
    }

    private function getResponsibleDepartment(\Modul $modul)
    {
        $responsible_department = \Institute::build(['id' => $modul->responsible_institute->institut_id]);
        if (!$responsible_department) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($responsible_department),
            ],
            self::RELATIONSHIP_DATA => $responsible_department,
        ];
    }

    private function addDepartments(array $relationships, $resource, $includeData)
    {
        $departments = $resource->assigned_institutes->orderBy('position')->map(function (\ModulInst $module_inst) {
            return \Institute::build(['id' => $module_inst->institut_id]);
        });

        $relationships[self::REL_DEPARTMENTS][self::RELATIONSHIP_DATA] = $departments;

        return $relationships;
    }

    private function addModuleComponentsRelationship(array $relationships, $resource, $includeData)
    {
        $relationships[self::REL_MODULE_COMPONENTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_MODULE_COMPONENTS),
            ],
        ];

        $relationships[self::REL_MODULE_COMPONENTS][self::RELATIONSHIP_DATA] = $resource->modulteile;

        return $relationships;
    }
}
