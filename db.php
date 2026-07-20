<?php
$pdo = new PDO(
  "mysql:host=allamericaatlanticco.mydomaincommysql.com;dbname=all_america_atlantic;charset=utf8mb4",
  "charlesf426",
  "Fletch426$",
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]
);
