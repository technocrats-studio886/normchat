# Postgres 16 (alpine, sama dengan sebelumnya) + pgvector untuk pencarian semantik ingatan Normi.
# Tetap alpine: pindah ke image berbasis Debian/glibc mengubah collation dan bisa merusak indeks teks yang ada.
FROM postgres:16-alpine

ARG PGVECTOR_VERSION=0.8.7
ARG PGVECTOR_SHA256=cac0b10c360f05b2d521200105ba3697e773d4cd3731f5a915a7e37ebe0bea85

RUN set -eux; \
    apk add --no-cache --virtual .build-pgvector build-base; \
    wget -O /tmp/pgvector.tar.gz "https://github.com/pgvector/pgvector/archive/refs/tags/v${PGVECTOR_VERSION}.tar.gz"; \
    echo "${PGVECTOR_SHA256}  /tmp/pgvector.tar.gz" | sha256sum -c -; \
    mkdir /tmp/pgvector; \
    tar -xzf /tmp/pgvector.tar.gz -C /tmp/pgvector --strip-components=1; \
    cd /tmp/pgvector; \
    # with_llvm=no: tanpa bitcode JIT, jadi tidak butuh clang yang versinya harus sama dengan LLVM image.
    make OPTFLAGS="" with_llvm=no; \
    make install with_llvm=no; \
    cd /; \
    rm -rf /tmp/pgvector /tmp/pgvector.tar.gz; \
    apk del .build-pgvector
