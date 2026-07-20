<?php

class Pagination {

    const DEFAULT_LIMIT = 20;
    const MAX_LIMIT     = 50;

    public static function getPageLimit() {
        $page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : self::DEFAULT_LIMIT;

        if ($page < 1)  $page  = 1;
        if ($limit < 1) $limit = self::DEFAULT_LIMIT;

        // Cap it — the limit goes straight into a LIMIT clause, so an
        // unbounded value lets one request pull the whole table.
        if ($limit > self::MAX_LIMIT) $limit = self::MAX_LIMIT;

        $offset = ($page - 1) * $limit;

        return [$page, $limit, $offset];
    }
}
