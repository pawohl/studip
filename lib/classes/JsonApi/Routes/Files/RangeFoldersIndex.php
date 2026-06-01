<?php

namespace JsonApi\Routes\Files;

class RangeFoldersIndex extends AbstractRangeIndex
{
    protected function getRangeResources(\User $user, \SimpleORMap $resource)
    {
        $rootFolder = \Folder::findTopFolder($resource->id)->getTypedFolder();

        return self::getFolderRecursive($rootFolder, $user);
    }
}
