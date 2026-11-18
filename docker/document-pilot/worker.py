"""Internal, serial converter. Input is always a byte stream, never a user URL."""
import json
import os
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
import subprocess
import tempfile

MODE = os.environ.get("DOCUMENT_MODE", "pdf")
MAX_BYTES = 8 * 1024 * 1024
converter = None


def pdf_document(path):
    global converter
    from docling.datamodel.base_models import InputFormat, ConversionStatus
    from docling.datamodel.pipeline_options import PdfPipelineOptions, TesseractCliOcrOptions
    from docling.document_converter import DocumentConverter, PdfFormatOption

    if converter is None:
        options = PdfPipelineOptions(do_ocr=True, do_table_structure=True,
                                     artifacts_path=Path(os.environ["DOCLING_SERVE_ARTIFACTS_PATH"]))
        options.ocr_options = TesseractCliOcrOptions(lang=["eng"])
        converter = DocumentConverter(format_options={InputFormat.PDF: PdfFormatOption(pipeline_options=options)})
    result = converter.convert(path, max_num_pages=35, max_file_size=MAX_BYTES)
    if result.status != ConversionStatus.SUCCESS:
        raise ValueError("PDF incomplete")
    doc = result.document
    text = doc.export_to_markdown()
    headers = []
    for number, table in enumerate(doc.tables, 1):
        grid = table.data.grid
        if grid:
            headers.append({"table": number, "rows": [[cell.text for cell in row] for row in grid[:3]]})
    if not text.strip() or len(text) > 100000 or len(json.dumps(headers)) > 50000:
        raise ValueError("PDF requires a smaller section")
    return json.dumps({"text": text, "tables": headers}, ensure_ascii=False).encode()


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != "/" + MODE:
            return self.send_error(404)
        length = int(self.headers.get("Content-Length", "0"))
        if not 0 < length <= MAX_BYTES:
            return self.send_error(413)
        try:
            with tempfile.TemporaryDirectory() as tmp:
                path = Path(tmp) / ("input." + MODE)
                path.write_bytes(self.rfile.read(length))
                if MODE == "ppt":
                    subprocess.run(["soffice", "-env:UserInstallation=" + (Path(tmp) / "profile").as_uri(),
                                    "--headless", "--convert-to", "pptx", "--outdir", tmp, str(path)],
                                   check=True, timeout=55, stdout=subprocess.DEVNULL)
                    result = path.with_suffix(".pptx")
                    if not 0 < result.stat().st_size <= MAX_BYTES:
                        raise ValueError("PPTX too large")
                    body = result.read_bytes()
                else:
                    body = pdf_document(path)
        except Exception as error:
            print("Conversion failed:", type(error).__name__, flush=True)
            return self.send_error(422)
        self.send_response(200)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


HTTPServer(("0.0.0.0", 8010), Handler).serve_forever()
