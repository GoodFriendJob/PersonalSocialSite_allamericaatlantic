<?php

class Pagination {
    public static function getPageLimit() {
        $page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 20;

        $offset = ($page - 1) * $limit;

        return [$page, $limit, $offset];
    }
}
