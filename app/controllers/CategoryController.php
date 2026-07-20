<?php
/**
 * Blog / forum categories.
 *
 * The feed's sport tabs used to be hardcoded HTML with no backing data, so
 * clicking one did nothing. They are now rendered from this endpoint and
 * carry a real category_id used to filter posts.
 */

class CategoryController
{
    /* GET categories */
    public static function index($params)
    {
        global $pdo;

        $stmt = $pdo->query("
            SELECT c.id, c.name, c.description, c.icon,
                   (SELECT COUNT(*) FROM posts p
                     WHERE p.category_id = c.id AND p.visibility = 'public') AS post_count
              FROM categories c
             ORDER BY c.name
        ");

        $categories = array_map(function ($row) {
            $row['id']         = (int)$row['id'];
            $row['post_count'] = (int)$row['post_count'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        Response::success(['categories' => $categories]);
    }

    /* POST categories — admin only */
    public static function create($params)
    {
        global $pdo;

        AdminMiddleware::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name  = trim($input['name'] ?? '');

        if ($name === '') {
            Response::error("Category name is required", 400);
            return;
        }

        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            Response::error("That category already exists", 409);
            return;
        }

        $pdo->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)")
            ->execute([$name, trim($input['description'] ?? '') ?: null, trim($input['icon'] ?? '') ?: null]);

        Response::success(['id' => (int)$pdo->lastInsertId(), 'name' => $name]);
    }

    /* DELETE categories/{id} — admin only */
    public static function delete($params)
    {
        global $pdo;

        AdminMiddleware::requireAdmin();

        $id = (int)$params['id'];

        // Posts keep existing, they just lose the category.
        $pdo->prepare("UPDATE posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            Response::error("Category not found", 404);
            return;
        }

        Response::success(['deleted' => $id]);
    }
}
