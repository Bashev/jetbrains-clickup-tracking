<?php
/*
 * @author       Kostadin Bashev | Webcode Ltd.
 * @copyright    Copyright (c) 2023 Webcode Ltd. (https://webcode.bg/)
 * @license      http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

ini_set('display_errors', 1);

header('Content-type: application/json');

require_once 'helpers.php';

require_once 'Clickup.php';

const STORAGE_DIR = 'storage';

$clickup = new Clickup();

// All Gitlab Urls.
if (preg_match('@^/api/v4/(\w+)(/((\w+)%2F)?(-?\d+)/(issues))?(/(\d+)/(add_spent_time))?@', $_SERVER['REQUEST_URI'], $matches) && isset($matches[1])) {
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 30;

    // Version
    if ($matches[1] === 'version') {
        $response = [
            "version"    => "15.8.1",
            "enterprise" => false
        ];

        echo json_encode($response);
        exit;
    }

    // Workaround to make initial validaton.
    if ($matches[1] === 'issues') {
        echo json_encode([]);
        exit;
    }

    // Projects
    if (count($matches) === 2 && $matches[1] === 'projects') {
        $spaces = paginate($clickup->getSpaces(), $page, $perPage);

        $response = [];
        if (!empty($spaces)) {
            foreach ($spaces as $id => $space) {
                $response[] = [
                    'id'      => $id,
                    'name'    => $space->name,
                    'web_url' => sprintf('%s://%s/api/space/%s',
                        $_SERVER['REQUEST_SCHEME'],
                        $_SERVER['SERVER_NAME'],
                        $space->id)
                ];
            }
        }

        echo json_encode($response);
        exit;
    }

    // Get Original Space Id.
    if (count($matches) >= 7 && isset($matches[5])) {
        $spaceId = $matches[5];
        $clickup->setSpaceId($spaceId);

        if (empty($clickup->getCurrentSpace())) {
            $clickup->setSpaceId(convertToInt($spaceId));
        }

        $teamId = $clickup->getCurrentSpace()->team_id;

        if ($spaceId < 0) {
            $spaceId = $clickup->getCurrentSpace()->id;
            $clickup->setSpaceId($spaceId);
        }
    }

    // Issues (Tasks)
    if (count($matches) === 7 && $matches[6] === 'issues' && empty($matches[3]) && empty($matches[4]) && isset($spaceId)) {

        $tasks = [];
        $clickupTasks = $clickup->getTasks();

        if (!empty($clickupTasks)) {
            $oldTaskIds = getTaskIds($spaceId);
            $taskIds = $oldTaskIds;

            foreach ($clickupTasks as $task) {
                // looks TaskID in the storage, if not found, add it.
                if (!in_array($task->id, $taskIds)) {
                    $taskIds[] = $task->id;
                    $taskId = count($taskIds);
                } else {
                    $taskId = array_search($task->id, $taskIds);
                }

                $tasks[$taskId] = $task;
            }

            // Save back to starage.
            if (count($oldTaskIds) !== count($clickupTasks)) {
                $storagePath = STORAGE_DIR . DIRECTORY_SEPARATOR . $spaceId;
                file_put_contents($storagePath, implode("\n", $taskIds));
            }
        }

        $tasks = paginate($tasks, $page, $perPage);

        if (!empty($tasks)) {
            $response = [];
            foreach ($tasks as $id => $task) {
                $response[] = [
                    'id'          => $id,
                    'iid'         => $id,
                    'title'       => sprintf('%s: %s', $task->id, $task->name),
                    'project_id'  => $matches[5],
                    'description' => $task->description,
                    'state'       => 'opened',
                    'created_at'  => convertToDate($task->date_created),
                    'updated_at'  => convertToDate($task->date_updated),
                    'issue_type'  => 'issue',
                    'web_url'     => $task->url,
                ];
            }

            echo json_encode($response);
        }

        exit;
    }

    if (count($matches) === 10 && $matches[9] === 'add_spent_time' && isset($_GET['duration']) && isset($spaceId) && isset($teamId)) {
        if (!empty($matches[5]) && !empty($matches[8])) {

            $taskId = getTaskIds($spaceId)[$matches[8]] ?? 0;

            if ($taskId) {
                preg_match('/(\d+)h\s(\d+)m/', $_GET['duration'], $durationString);

                if (isset($durationString[1]) && isset($durationString[2])) {
                    /**
                     * (Hours * 60) + Minutes = Minutes
                     * Minutes * 60 = Seconds
                     * Seconds * 1000 = Microseconds.
                     */
                    $duration = (((int)$durationString[1]*60) + (int)$durationString[2])*60*1000;

                    $response = $clickup->addTracking($teamId,$taskId, $duration);
                    if (!isset($response->ECODE)) {
                        header('HTTP/1.1 201 Created', true, 201);
                        exit;
                    }

                    echo json_encode($response);
                    exit;
                }

                echo json_encode(['success' => false, 'message' => 'Nothing to track.']);
                exit;
            }
        }

        echo json_encode(['success' => false, 'message' => 'Task Not Found.']);
        exit;
    }
}

header('HTTP/1.1 404 Not Found');
exit;