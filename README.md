# ĐỀ 21: HỆ THỐNG QUẢN LÝ CỬA HÀNG / POS
**Sinh viên thực hiện:** Lý Thị Hà  
**Mã sinh viên:** DTC241200006  

---
## Hướng dẫn triển khai và khởi chạy

## 1: TRIỂN KHAI VÀ KHỞI CHẠY HẠ TẦNG DOCKER
### 1. Di chuyển vào thư mục dự án và khởi động hệ thống
```bash
cd ~/DTC241200006
docker compose up -d --build

### 1. Di chuyển vào thư mục dự án và khởi động hệ thống
```bash
cd ~/DTC241200006
docker compose up -d --build
### 3. Kiểm tra trạng thái hoạt động các container

docker compose ps

## 2: CỔNG DỊCH VỤ VÀ CHỨNG CHỈ BẢO MẬT SSL/TLS
### 1. Danh sách cổng truy cập
Web POS (HTTPS): https://localhost (Port 443 / Port 80)

phpMyAdmin: http://localhost:8080

Prometheus: http://localhost:9090

Grafana: http://localhost:13000

Loki: http://localhost:3100
### 2. Kiểm tra chứng chỉ SSL/TLS
Bash
curl -kIv https://localhost

## 3: THIẾT LẬP GIÁM SÁT TÀI NGUYÊN (PROMETHEUS & CADVISOR)
### 1. Kiểm tra trạng thái Target trong Prometheus
Truy cập: http://localhost:9090/targets

Xác nhận: Tất cả các endpoint thu thập metrics (cAdvisor, Node Exporter, Nginx) đều hiển thị trạng thái màu xanh (UP).

### 2. Dashboard giám sát trên Grafana
Đăng nhập Grafana tại http://localhost:13000.

Kiểm tra Data Source Prometheus kết nối thành công.

Giám sát các biểu đồ: CPU Usage, RAM Usage, Network I/O và Container Health.


## 4: CẤU HÌNH LUẬT CẢNH BÁO AN TOÀN THÔNG TIN (ALERT RULES)
### 1. Thiết lập cảnh báo phát hiện quét lỗ hổng Web
Tạo luật nhận diện lưu lượng bất thường gây lỗi HTTP 404/403 tăng vọt trong cấu hình cảnh báo:

YAML
groups:
  - name: pos_security_alerts
    rules:
      - alert: WebVulnerabilityScanDetected
        expr: rate(nginx_http_requests_total{status=~"404|403"}[1m]) > 0.5
        for: 30s
        labels:
          severity: critical
        annotations:
          summary: "Phat hien hanh vi do quet lo hong Web POS"
          description: "So luong request loi 404/403 vuot nguong bat thuong tren Web Server Nginx."
### 2. Xác minh trạng thái kích hoạt cảnh báo
Kiểm tra tại http://localhost:9090/alerts hoặc mục Alerting > Alert rules trên Grafana.

Khi có tấn công dò quét, trạng thái chuyển từ Normal sang Firing.

## 5: MÔ PHỎNG TẤN CÔNG THỰC NGHIỆM
Giả lập hành vi tấn công dò quét tệp cấu hình nhạy cảm (.env, wp-login.php, config.php):

### 1. Thực thi chuỗi request dò quét liên tục

for i in {1..20}; do
  curl -k -s "https://localhost/.env" > /dev/null
  curl -k -s "https://localhost/wp-login.php" > /dev/null
done
### 2. Kiểm tra log thô tại Web Server Nginx

docker logs pos_nginx --tail 10
Xác nhận: Nginx ghi nhận các truy vấn GET /.env HTTP/1.1 404 và GET /wp-login.php HTTP/1.1 404.

## 6: TRUY VẾT VÀ PHÂN TÍCH SỰ CỐ BẰNG LOGQL (LOKI)
### 1. Thu thập dữ liệu
Promtail tự động đọc log từ container Docker và chuyển tiếp về dịch vụ Loki qua cổng 3100.

Grafana Explore kết nối trực tiếp đến datasource Loki để phân tích.

### 2. Truy vấn điều tra sự cố (LogQL)
Tại giao diện Grafana Explore, nhập câu lệnh:

Đoạn mã
{job="docker-logs"} !~ "grafana" |= "GET /.env"
