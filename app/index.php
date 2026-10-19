<?php
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'pos_db';
$user = getenv('DB_USER') ?: 'pos_user';
$pass = getenv('DB_PASSWORD') ?: 'PosSecurePass2026!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

$message = "";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Xử lý khi tạo Đơn hàng mới (Bán hàng)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
        $product_id = intval($_POST['product_id']);
        $quantity   = intval($_POST['quantity']);

        if ($product_id > 0 && $quantity > 0) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if ($product && $product['stock'] >= $quantity) {
                $total_amount = $product['price'] * $quantity;

                // 1. Trừ kho
                $updateStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $updateStmt->execute([$quantity, $product_id]);

                // 2. Lưu lịch sử đơn hàng
                $orderStmt = $pdo->prepare("INSERT INTO orders (product_name, quantity, total_amount) VALUES (?, ?, ?)");
                $orderStmt->execute([$product['name'], $quantity, $total_amount]);

                $message = "Thanh toán thành công! Đã trừ $quantity sản phẩm.";
            } else {
                $message = "Lỗi: Số lượng tồn kho không đủ!";
            }
        }
    }

    // Xử lý khi Nhập sản phẩm mới
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
        $name  = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);

        if (!empty($name) && $price > 0 && $stock >= 0) {
            $addStmt = $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?, ?, ?)");
            $addStmt->execute([$name, $price, $stock]);
            $message = "Đã thêm sản phẩm mới vào kho thành công!";
        }
    }

    // Lấy danh sách sản phẩm
    $products = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();

    // Lấy danh sách hóa đơn gần nhất
    $orders = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 10')->fetchAll();

} catch (\PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ Thống Quản Lý Cửa Hàng / POS</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 30px; background-color: #f8fafc; color: #1e293b; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 25px; }
        .header h1 { margin: 0 0 5px 0; font-size: 24px; color: #0f172a; }
        .header p { margin: 0; color: #64748b; font-size: 14px; }
        .alert { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .card { background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .card h3 { margin-top: 0; margin-bottom: 15px; font-size: 16px; color: #1e293b; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #475569; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn { width: 100%; padding: 10px; background-color: #2563eb; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; margin-top: 5px; }
        .btn:hover { background-color: #1d4ed8; }
        .section-title { font-size: 18px; font-weight: 700; margin: 25px 0 15px 0; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        th, td { padding: 12px 16px; text-align: left; font-size: 14px; }
        th { background-color: #f1f5f9; font-weight: 600; color: #334155; }
        tr:not(:last-child) { border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Đề 21: Hệ Thống Quản Lý Cửa Hàng / POS</h1>
            <p><strong>Sinh viên thực hiện:</strong> Lý Thị Hà | <strong>MSNV:</strong> DTC241200006</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid">
            <!-- Form Tạo Đơn Hàng -->
            <div class="card">
                <h3>Tạo Đơn Hàng (Bán Hàng)</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_order">
                    <div class="form-group">
                        <label>Chọn mặt hàng:</label>
                        <select name="product_id" required>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (Kho: <?= $p['stock'] ?> - <?= number_format($p['price'], 0, ',', '.') ?> đ)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Số lượng:</label>
                        <input type="number" name="quantity" value="1" min="1" required>
                    </div>
                    <button type="submit" class="btn">Thanh toán & Xuất hóa đơn</button>
                </form>
            </div>

            <!-- Form Nhập Sản Phẩm Mới -->
            <div class="card">
                <h3>Nhập Sản Phẩm Mới</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-group">
                        <label>Tên sản phẩm:</label>
                        <input type="text" name="name" placeholder="Nhập tên sản phẩm..." required>
                    </div>
                    <div class="form-group">
                        <label>Đơn giá (VNĐ):</label>
                        <input type="number" name="price" placeholder="50000" required>
                    </div>
                    <div class="form-group">
                        <label>Số lượng trong kho:</label>
                        <input type="number" name="stock" placeholder="10" required>
                    </div>
                    <button type="submit" class="btn">Lưu vào kho</button>
                </form>
            </div>
        </div>

        <!-- Bảng 1: Danh Sách Sản Phẩm & Tồn Kho Nhanh -->
        <div class="section-title">Danh Sách Sản Phẩm & Tồn Kho Nhanh</div>
        <table>
            <thead>
                <tr>
                    <th>Mã SP</th>
                    <th>Tên Sản Phẩm</th>
                    <th>Đơn Giá</th>
                    <th>Số Lượng Tồn</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td>#<?= $p['id'] ?></td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td><?= number_format($p['price'], 0, ',', '.') ?> đ</td>
                    <td><?= $p['stock'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Bảng 2: Lịch Sử Hóa Đơn Bán Hàng Gần Nhất -->
        <div class="section-title">Lịch Sử Hóa Đơn Bán Hàng Gần Nhất</div>
        <table>
            <thead>
                <tr>
                    <th>Mã HĐ</th>
                    <th>Sản Phẩm</th>
                    <th>SL</th>
                    <th>Tổng Tiền</th>
                    <th>Thời Gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Chưa có hóa đơn nào được tạo.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= htmlspecialchars($o['product_name']) ?></td>
                        <td><?= $o['quantity'] ?></td>
                        <td><strong><?= number_format($o['total_amount'], 0, ',', '.') ?> đ</strong></td>
                        <td><?= $o['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
