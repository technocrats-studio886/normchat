FROM python:3.12-slim
RUN apt-get update && apt-get install -y --no-install-recommends libreoffice-impress && rm -rf /var/lib/apt/lists/*
ENV DOCUMENT_MODE=ppt HOME=/tmp
USER 65534
COPY worker.py /worker.py
ENTRYPOINT ["python", "/worker.py"]
