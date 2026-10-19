
## Đề tài: HỆ THỐNG QUẢN LÝ CỬA HÀNG /POS 
Sinh viên thực hiện: Lý Thị Hà
Mã sinh viên: DTC241200006
---
## HƯỚNG DẪN TRIỂN KHAI VÀ KHỞI CHẠY

## 1. Giới thiệu tổng quan
Hệ thống **POS / Store Management** được xây dựng và triển khai trên nhánh `bai-thuc-hanh-moi` dựa trên mô hình Docker Containerization. Đồ án tập trung giải quyết các bài toán cốt lõi:
- **Đóng gói và triển khai ứng dụng** đa container (App, MySQL, Nginx, Prometheus, Grafana, Loki, Promtail).
- **Bảo mật hệ thống (Hardening)** toàn diện qua 5 trụ cột.
- **Giám sát hệ thống 3 tầng** (Container, Web Server, Database/Host) kết hợp thu thập log tập trung.
- **Ứng cứu sự cố an toàn thông tin (Incident Response)**: Kịch bản phát hiện dò quét/tấn công (Brute-force), truy vết log thời gian thực qua Loki và bắn cảnh báo tự động (Alerting).

---

## 2. Kiến trúc hạ tầng & Cấu trúc Service
Hệ thống sử dụng `docker-compose` với các thành phần chính:
1. **Web Server (Nginx)**: Đóng vai trò Reverse Proxy, định tuyến kết nối, cấu hình HTTPS và bảo mật tiêu đề HTTP Headers.
2. **Application (App)**: Xử lý nghiệp vụ POS.
3. **Database (MySQL)**: Lưu trữ cơ sở dữ liệu, được cách ly hoàn toàn trong mạng nội bộ.
4. **Monitoring Stack**:
   - **Prometheus & cAdvisor**: Thu thập metrics tài nguyên container và host.
   - **Loki & Promtail**: Thu thập, phân tích và quản lý log tập trung từ Nginx và các dịch vụ.
   - **Grafana**: Trực quan hóa dữ liệu giám sát và quản lý Alerting.

---

## 3. Các biện pháp Bảo mật Hệ thống 

1. **Non-root Containers**: Các container được cấu hình chạy với tài khoản người dùng đặc quyền thấp (non-root), hạn chế tối đa rủi ro leo thang đặc quyền khi bị tấn công.
2. **Network Isolation**: Xây dựng mạng nội bộ riêng biệt (`db-private`). Database MySQL chỉ nhận kết nối nội bộ từ App, hoàn toàn không public port trực tiếp ra Internet.
3. **Strong Credentials**: Quản lý thông tin nhạy cảm, tài khoản và mật khẩu cơ sở dữ liệu thông qua tệp biến môi trường bảo mật (`.env`).
4. **Least-Privilege Access**: Phân quyền truy cập tối thiểu cho các tài khoản cơ sở dữ liệu và quản trị viên.
5. **Nginx Security Headers & HTTPS**: 
   - Kích hoạt SSL/TLS (HTTPS).
   - Bổ sung các header bảo mật chống tấn công phổ biến:
     - `X-Frame-Options: SAMEORIGIN` (Chống Clickjacking).
     - `X-Content-Type-Options: nosniff` (Chống MIME-sniffing).
     - `X-XSS-Protection: 1; mode=block` (Chống XSS cơ bản).

---

## 4. Hệ thống Giám sát 3 Tầng

Hệ thống giám sát được tích hợp đồng bộ trên giao diện **Grafana**:
- **Tầng 1 (Container Metrics - cAdvisor & Prometheus)**: Giám sát mức độ tiêu thụ CPU, RAM thực tế của từng container (`pos_app_v2`, `pos_db_v2`, `pos_nginx_v2`,...).
- **Tầng 2 (Web Server & LogQL - Loki & Promtail)**: Thu thập toàn bộ log truy cập, mã trạng thái HTTP (200, 401, 404,...) của Nginx Web Server.
- **Tầng 3 (Database & Host - Node Exporter)**: Giám sát tài nguyên phần cứng, tải CPU và bộ nhớ của máy chủ chủ quản.

---

## 5. Mô Phỏng Ứng cứu Sự cố An toàn Thông tin (Security Incident Response)

Đồ án hiện thực hóa quy trình **Detect - Trace - Alert** đối với sự cố an toàn thông tin (mô phỏng tấn công Brute-force / Dò quét mật khẩu):

1. **Phát hiện sự cố (Detection)**:
   - Kẻ tấn công thực hiện gửi dồn dập các request lỗi hoặc cố tình đăng nhập sai liên tục vào hệ thống POS.
2. **Truy vết Log (Tracing with Loki)**:
   - Quản trị viên sử dụng cú pháp **LogQL** trên trang **Explore** của Loki để lọc log:
     ```logql
     {service_name="nginx"} |~ "401|404|POST|GET"
     ```
   - Trích xuất chính xác địa chỉ IP nguồn (`192.168.171.1`), thời điểm xảy ra sự cố và đường dẫn bị nhắm mục tiêu (ví dụ: `/admin-login`, `/admin`).
3. **Cảnh báo tự động (Grafana Alerting)**:
   - Thiết lập Alert Rule: Khi tổng số lượng request lỗi/bất thường vượt ngưỡng **`Is above 5`** trong vòng `1 phút`:
     - Trạng thái Grafana Alert chuyển từ `Normal` sang **`FIRING` (Màu đỏ)**.
     - Kịp thời cảnh báo đội ngũ quản trị vận hành để xử lý khóa IP hoặc ngăn chặn kết nối độc hại.

---

