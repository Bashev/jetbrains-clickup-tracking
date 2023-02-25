<?php
/*
 * @author       Kostadin Bashev | Webcode Ltd.
 * @copyright    Copyright (c) 2023 Webcode Ltd. (https://webcode.bg/)
 * @license      http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Paginate results same as gitlab.
 *
 * @param $data
 * @param $page
 * @param $perPage
 * @return array
 */
function paginate($data, $page, $perPage): array
{
    $offset = ($page - 1)*$perPage;
    return array_slice($data, $offset, $perPage, true);
}

/**
 * Workaround for long int ids.
 *
 * @param $value
 * @return int
 */
function convertToInt($value): int
{
    return $value > 2147483647 ? - (int)substr($value, 0, 9) : $value;
}

/**
 * Convert Unixtimestamp to Gitlab format
 *
 * @param $timestamp
 * @return string
 */
function convertToDate($timestamp): string
{
    return date("Y-m-d\TH:i:s.u\Z", round($timestamp/1000));
}

/**
 * Get Original Task IDs from local text storage.
 *
 * Task IDs need to be int, but in clickup they are string and we need to convert them.
 */
function getTaskIds($space): array
{
    // Make storage this if not exists.
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR);
    }

    $storagePath = STORAGE_DIR . DIRECTORY_SEPARATOR . $space;

    // Create file is not exists.
    if (!file_exists($storagePath)) {
        file_put_contents($storagePath, '');
    }

    // Read current storage.
    $storage = file_get_contents($storagePath);

    // Make an array with current entries.
    $taskIds = explode("\n", $storage);

    // Filter empty rows and return data.
    return array_filter($taskIds);
}
