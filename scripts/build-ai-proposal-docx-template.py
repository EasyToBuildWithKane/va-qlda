"""Build Word template with ${placeholders} from public/files/pdx.docx."""
import re
import shutil
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "public/files/pdx.docx"
OUT = ROOT / "resources/templates/ai-purchase-proposal.docx"

REPLACEMENTS = [
    ("Đăng ký sử dụng công cụ UX Pilot AI phục vụ công việc", "${subject_about}"),
    ("Ban Giám đốc", "${send_to_part1}"),
    ("Phòng Công nghệ & Phòng Kế Toán", "${send_to_part2}"),
    ("Nguyễn Lê Thanh Kiều", "${proposer_name}"),
    ("Nhân viên Phân tích nghiệp vụ", "${proposer_position}"),
    ("Phòng Công Nghệ", "${proposer_department}"),
    (
        "Đề xuất đăng ký sử dụng công cụ UX Pilot AI (gói Pro – tài khoản cá nhân, thanh toán theo tháng) nhằm hỗ trợ công tác phân tích nghiệp vụ, thiết kế giao diện hệ thống và xây dựng prototype phục vụ các dự án phần mềm của Nhà trường. \nCông cụ cung cấp các tính năng hỗ trợ xây dựng User Flow, Wireframe, Sitemap, Prototype và xuất thiết kế sang Figma, giúp tăng tốc quá trình phân tích yêu cầu, thiết kế giải pháp và trao đổi nghiệp vụ giữa các bên liên quan.",
        "${proposal_content}",
    ),
    (
        "Tăng tốc quá trình phân tích và mô hình hóa nghiệp vụ.\nHỗ trợ xây dựng Wireframe và Prototype trực quan.\nGiảm thời gian thiết kế giao diện ban đầu.\nNâng cao chất lượng tài liệu đặc tả nghiệp vụ và yêu cầu hệ thống.\nHỗ trợ trao đổi yêu cầu hiệu quả giữa BA, Developer và người dùng.\nHạn chế phát sinh thay đổi yêu cầu trong quá trình triển khai dự án.\nGóp phần nâng cao chất lượng sản phẩm và hiệu quả triển khai dự án công nghệ thông tin.",
        "${objectives}",
    ),
    ("UX Pilot AI - Pro Plan", "${tool_product_line}"),
    ("~ 600.000", "${cost_monthly_formatted}"),
    ("01 nhân sự", "${staff_count_line}"),
    ("kieunlt@hcm.vaschools.edu.vn", "${recipient_email}"),
    ("0829258793", "${recipient_phone}"),
    ("phongcongnghe@vaschools.edu.vn", "${registration_email}"),
    ("08/06/2026", "${planned_use_date}"),
    ("Thứ Ba, ngày 2 tháng 06 năm 2026", "${doc_date}"),
]

def main() -> None:
    OUT.parent.mkdir(parents=True, exist_ok=True)
    tmp = OUT.with_suffix(".tmp.zip")
    with zipfile.ZipFile(SRC, "r") as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
        xml = zin.read("word/document.xml").decode("utf-8")
        for old, new in REPLACEMENTS:
            xml = xml.replace(old, new)
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename == "word/document.xml":
                data = xml.encode("utf-8")
            zout.writestr(item, data)
    shutil.move(tmp, OUT)
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    main()
