<?php
/*
 * @author       Kostadin Bashev | Webcode Ltd.
 * @copyright    Copyright (c) 2023 Webcode Ltd. (https://webcode.bg/)
 * @license      http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Clickup
{
    const CLICKUP_API_URL = 'https://api.clickup.com/api/v2/';

    private object $user;

    private array $teams = [];

    private array $spaces = [];

    private array $tasks = [];

    private int $spaceId;

    public function __construct()
    {
        if (isset($_SERVER['HTTP_PRIVATE_TOKEN']) && $response = $this->apiCall('user')) {
            if (!empty($response) && isset($response->user->id)) {
                $this->user = $response->user;
            }
        }

        if (empty($this->user)) {
            header('HTTP/1.0 403 Forbidden');
            exit();
        }
    }

    private function apiCall(string $url, array $params = [])
    {
        $curl = curl_init();
        $options = [
            CURLOPT_URL            => self::CLICKUP_API_URL . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => !empty($params) ? 'POST' : 'GET',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . $_SERVER['HTTP_PRIVATE_TOKEN']
            ],
        ];

        if (!empty($params)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($params);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200) {
            curl_close($curl);

            return json_decode($response);
        }


        curl_close($curl);

        return json_decode($response);
    }

    /**
     * Get All Teams (Companies) on which user is added.
     *
     * @return array
     */
    private function getTeams(): array
    {
        if (empty($this->teams)) {
            $userTeams = [];
            $teams = $this->apiCall('team');
            if (isset($teams->teams)) {
                foreach ($teams->teams as $team) {
                    if (isset($team->id) && isset($team->members)) {
                        foreach ($team->members as $member) {
                            if ($member->user->email == $this->user->email) {
                                $userTeams[] = $team->id;
                            }
                        }
                    }
                }
            }

            $this->teams = $userTeams;
        }

        return $this->teams;
    }

    /**
     * Get All Spaces to logged user.
     *
     * @return array
     */
    public function getSpaces(): array
    {
        if (empty($this->spaces)) {
            $userSpaces = [];
            foreach ($this->getTeams() as $userTeam) {
                $spaces = $this->apiCall(sprintf('team/%s/space?archived=false', $userTeam));
                if (isset($spaces->spaces)) {
                    foreach ($spaces->spaces as $space) {
                        $space->team_id = $userTeam;
                        $userSpaces[convertToInt($space->id)] = $space;
                    }
                }
            }

            $this->spaces = $userSpaces;
        }

        return $this->spaces;
    }

    public function setSpaceId(int $spaceId): void
    {
        $this->spaceId = $spaceId;
    }

    /**
     * @return int
     */
    public function getSpaceId(): int
    {
        return $this->spaceId;
    }

    public function getCurrentSpace(): ?object
    {
        if (empty($this->spaces)) {
            $this->getSpaces();
        }

        return $this->spaces[$this->getSpaceId()] ?? null;
    }

    private function getFolders(): array
    {
        $spaceFolders = [];
        $folders = $this->apiCall(sprintf('space/%s/folder', $this->getSpaceId()));
        if (isset($folders->folders)) {
            foreach ($folders->folders as $folder) {
                $spaceFolders[] = $folder;
            }
        }

        return $spaceFolders;
    }

    /**
     * Get Lists related to user spaces.
     *
     * @return array
     */
    private function getLists(): array
    {
        $userLists = [];

        if ($folders = $this->getFolders()) {
            foreach ($folders as $folder) {
                foreach ($folder->lists as $list) {
                    $userLists[] = $list->id;
                }
            }
        }

        $lists = $this->apiCall(sprintf('space/%s/list', $this->getSpaceId()));
        if (isset($lists->lists)) {
            foreach ($lists->lists as $list) {
                $userLists[] = $list->id;
            }
        }

        return $userLists;
    }

    /**
     * Get All tasks for user.
     *
     * @return array
     */
    public function getTasks(): array
    {
        if (empty($this->tasks)) {
            $userTasks = [];
            $queryParams = http_build_query([
                'include_closed' => !isset($_GET['state']) || $_GET['state'] !== 'opened',
                'archived'       => false,
                'subtasks'       => true,
                'assignees'      => [$this->user->id]
            ]);

            foreach ($this->getLists() as $userList) {
                $tasks = $this->apiCall(sprintf('list/%s/task?%s', $userList, $queryParams));
                if (isset($tasks->tasks)) {
                    $userTasks = array_merge($userTasks, $tasks->tasks);
                }
            }

            $this->tasks = $userTasks;
        }

        return $this->tasks;
    }

    public function addTracking($teamId, $taskId, $duration)
    {
        $tracking = [
            'description' => 'Automatically tracked by PHPStorm',
            'tags'        => [
                [
                    'name'   => 'Development',
                    'tag_bg' => '#BF55EC',
                    'tag_fg' => '#BF55EC'
                ]
            ],
            'start'       => round(microtime(true)*1000) - $duration,
            'billable'    => true,
            'duration'    => $duration,
            'assignee'    => $this->user->id,
            'tid'         => $taskId
        ];

        return $this->apiCall(sprintf('team/%s/time_entries', $teamId), $tracking);
    }
}
