<?php

class VipsFileRefs extends Migration
{
    public function description()
    {
        return 'update download URLs in exercise text to use the file_ref id';
    }

    public function up()
    {
        $db = DBManager::get();

        $sql = "SELECT range_id, file_id, file_refs.id AS file_ref_id
                FROM folders JOIN file_refs ON folder_id = folders.id
                WHERE folder_type = 'ExerciseFolder'";
        $data = $db->query($sql);

        $update = $db->prepare('UPDATE etask_tasks SET description = ?, task = ? WHERE id = ?');

        // replace file_ref_id with file_id in affected tasks
        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $task_id = $row['range_id'];
            $file_id = $row['file_id'];
            $file_ref_id = $row['file_ref_id'];
            $task = $db->fetchOne('SELECT * FROM etask_tasks WHERE id = ?', [$task_id]);

            $description = str_replace($file_id, $file_ref_id, $task['description']);
            $task_json = str_replace($file_id, $file_ref_id, $task['task']);

            if ($description !== $task['description'] || $task_json !== $task['task']) {
                $update->execute([$description, $task_json, $task_id]);
            }
        }
    }
}
