<?php
session_start();

$host = 'db';
$db   = 'pos_db';
$user = 'pos_user';
$pass = 'PosSecurePass2026!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Kết nối CSDL thất bại: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $prod_id = (int)$_POST['product_id'];
    $qty = (int)$_POST['quantity'];

    $stmt = $pdo->prepare("SELECT price, stock FROM products WHERE id = ?");
    $stmt->execute([$prod_id]);
    $prod = $stmt->fetch();

    if ($prod && $prod['stock'] >= $qty && $qty > 0) {
        $total = $prod['price'] * $qty;
        $orderStmt = $pdo->prepare("INSERT INTO orders (product_id, quantity, total_price) VALUES (?, ?, ?)");
        $orderStmt->execute([$prod_id, $qty, $total]);

        $updateStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $updateStmt->execute([$qty, $prod_id]);
        $_SESSION['message'] = "Thanh toán thành công! Đã trừ $qty sản phẩm.";
    } else {
        $_SESSION['message'] = "Lỗi: Tồn kho không đủ!";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    if (!empty($name) && $price > 0 && $stock >= 0) {
        $stmt = $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?, ?, ?)");
        $stmt->execute([$name, $price, $stock]);
        $_SESSION['message'] = "Thêm sản phẩm thành công!";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

$message = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
$orders = $pdo->query("SELECT o.id, p.name as product_name, o.quantity, o.total_price, o.created_at FROM orders o JOIN products p ON o.product_id = p.id ORDER BY o.id DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ thống Quản lý Cửa hàng / POS - DTC241200006</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 25px; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1000px; margin: auto; }
        .header { background: #1e40af; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert { background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        input, select, button { width: 100%; padding: 10px; margin-top: 8px; margin-bottom: 12px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        button { background: #2563eb; color: white; border: none; font-weight: bold; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Đề 21: Hệ thống Quản lý Cửa hàng / POS</h2>
        <p>Sinh viên thực hiện: Lý Thị Hà - MSV: DTC241200006</p>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>Tạo Đơn Hàng (Bán Hàng)</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_order">
                <label>Chọn mặt hàng:</label>
                <select name="product_id" required>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (Kho: <?= $p['stock'] ?> - <?= number_format($p['price']) ?> đ)</option>
                    <?php endforeach; ?>
                </select>
                <label>Số lượng:</label>
                <input type="number" name="quantity" min="1" value="1" required>
                <button type="submit">Thanh toán & Xuất hóa đơn</button>
            </form>
        </div>

        <div class="card">
            <h3>Nhập Sản Phẩm Mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_product">
                <label>Tên sản phẩm:</label>
                <input type="text" name="name" required placeholder="Nhập tên sản phẩm...">
                <label>Đơn giá (VNĐ):</label>
                <input type="number" name="price" min="1000" required placeholder="50000">
                <label>Số lượng trong kho:</label>
                <input type="number" name="stock" min="1" required placeholder="10">
                <button type="submit">Lưu vào kho</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom: 25px;">
        <h3>Danh Sách Sản Phẩm & Tồn Kho Nhanh</h3>
        <table>
            <thead>
                <tr><th>Mã SP</th><th>Tên Sản Phẩm</th><th>Đơn Giá</th><th>Số Lượng Tồn</th></tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>#<?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= number_format($p['price']) ?> đ</td>
                        <td><strong><?= $p['stock'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Lịch Sử Hóa Đơn Bán Hàng Gần Nhất</h3>
        <table>
            <thead>
                <tr><th>Mã HĐ</th><th>Sản Phẩm</th><th>SL</th><th>Tổng Tiền</th><th>Thời Gian</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= htmlspecialchars($o['product_name']) ?></td>
                        <td><?= $o['quantity'] ?></td>
                        <td><?= number_format($o['total_price']) ?> đ</td>
                        <td><?= $o['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
