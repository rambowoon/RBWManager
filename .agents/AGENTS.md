# Multi-Model Execution & Development Rules

## 1. Tối ưu hóa theo độ phức tạp tác vụ
- **Tác vụ đơn giản / UI / File đơn lẻ (HTML, CSS, text, chỉnh sửa nhỏ)**: Thực thi chỉnh sửa trực tiếp ngay lập tức, phản hồi ngắn gọn, không giải thích dông dài, không cần lập kế hoạch rườm rà.
- **Tác vụ phức tạp / Logic hệ thống (Deploy, Database, Refactor đa file, Debug)**: Cho phép suy luận kỹ lưỡng, kiểm tra an toàn dữ liệu trước khi thực thi để đảm bảo độ chính xác tuyệt đối.

## 2. Ưu tiên sử dụng MCP Tools
- **Luôn ưu tiên tận dụng các công cụ MCP khả dụng** để xử lý tác vụ:
  - Sử dụng `codebase-memory` (`detect_changes` / `index_repository` / `search_graph`): Luôn tự động đồng bộ và truy xuất kiến trúc/quan hệ hàm, class trong toàn bộ dự án.
  - Sử dụng `sequential-thinking` cho các bài toán phân tích logic phức tạp, debug sâu.
  - Sử dụng `memory` để ghi nhớ các ngữ cảnh đặc thù của dự án.
  - Sử dụng `figma` để đọc design/tokens khi làm việc với giao diện Figma.
  - Sử dụng `firecrawl` khi cần cào dữ liệu, tra cứu tài liệu web hoặc tài liệu kỹ thuật bên ngoài.

## 3. Quy chuẩn thực thi Code & Terminal
- Thao tác trực tiếp lên file, ưu tiên code hoàn chỉnh, không dùng placeholder (TODO/...).
- Tránh chạy vòng lặp terminal không cần thiết trừ khi tác vụ yêu cầu xác minh lỗi, test cú pháp hoặc chạy git commit/push.
- Phản hồi tập trung vào kết quả công việc, súc tích và dễ theo dõi.

## 4. Kế thừa & Tuân thủ triệt để Skill
- **Kích hoạt một lần, áp dụng xuyên suốt**: Khi người dùng đã gọi hoặc chỉ định một skill (ví dụ: `figma-to-nasanic-ui`), agent tự động duy trì chế độ tuân thủ 100% quy chuẩn của skill đó cho toàn bộ các lượt tương tác tiếp theo trong dự án.
- **Tuân thủ đầy đủ, không làm một nửa**: Mọi thành phần mã nguồn (Form, Input, Button, Slider, CSS, Image helper, Validation) phát sinh sau đó BẮT BUỘC phải áp dụng đầy đủ các quy tắc đã nêu trong skill, tuyệt đối không viết theo thói quen hay bỏ qua bất kỳ quy chuẩn nào.