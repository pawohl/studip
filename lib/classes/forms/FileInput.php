<?php

namespace Studip\Forms;

class FileInput extends Input
{

    public function render()
    {
        $template = $GLOBALS['template_factory']->open('forms/file_input');
        $template->title = $this->title;
        $template->name = $this->name;
        $template->folder = $this->value;
        $template->id = md5(uniqid());
        $template->uploadUrl = $this->attributes['upload_url'];
        $template->multiple = $this->attributes['multiple'] ?? false;
        $template->accept = $this->attributes['accept'] ?? '*/*';
        $template->required = $this->required ?? false;

        return $template->render();
    }

}
