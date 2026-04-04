ARG SOURCE_IMAGE="php:8.4-cli"

############################
# Stage 1: build tree-sitter + yaml grammar
############################
FROM ${SOURCE_IMAGE} AS build

ARG TREE_SITTER_REF=0.26.8
ARG TREE_SITTER_YAML_REF=0.7.2

# 1) System deps for building C libs
RUN apt-get update && apt-get install -y --no-install-recommends \
    git ca-certificates curl \
    build-essential pkg-config \
    clang libclang-dev \
    && rm -rf /var/lib/apt/lists/*

# 2) Install cargo
ENV CARGO_HOME=/usr/local/cargo \
    RUSTUP_HOME=/usr/local/rustup \
    PATH=/usr/local/cargo/bin:${PATH}
# tree-sitter CLI can be installed via cargo (recommended in docs.rs)
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y --profile minimal

# 3) Install tree-sitter CLI (locked). Alternative: npm install tree-sitter-cli,
# but cargo --locked is recommended and documented.
RUN cargo install --locked tree-sitter-cli --version ${TREE_SITTER_REF}

# 4) Build and install libtree-sitter
# According to Getting Started, running make inside the repository is enough.
RUN git clone --depth 1 --branch v${TREE_SITTER_REF} https://github.com/tree-sitter/tree-sitter.git /tmp/tree-sitter \
    && make -C /tmp/tree-sitter \
    && make -C /tmp/tree-sitter install PREFIX=/usr/local

# 5) Build and install tree-sitter-yaml as shared lib
RUN git clone --depth 1 --branch v${TREE_SITTER_YAML_REF} https://github.com/tree-sitter-grammars/tree-sitter-yaml.git /tmp/tree-sitter-yaml \
    && cd /tmp/tree-sitter-yaml \
    # schema is optional: core/json/legacy (see Makefile). \
    && make YAML_SCHEMA=core \
    # make runs `tree-sitter generate` if needed \
    && make install PREFIX=/usr/local

# sanity check: show that the libraries were actually created
RUN ls -la /usr/local/lib/libtree-sitter* /usr/local/lib/libtree-sitter-yaml*

############################
# Stage 2: runtime
############################
FROM ${SOURCE_IMAGE} AS runtime

# 6) Install extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    libffi-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install -j"$(nproc)" ffi
RUN pecl install pcov && docker-php-ext-enable pcov

# 7) Enable FFI
# php ini: ffi.enable = true/false/preload
RUN { \
      echo "ffi.enable=true"; \
      echo "ffi.preload="; \
    } > /usr/local/etc/php/conf.d/99-ffi.ini

# 8) Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 9) Copy .so files from the build stage
COPY --from=build /usr/local/lib/libtree-sitter* /usr/local/lib/
COPY --from=build /usr/local/include/tree_sitter /usr/local/include/tree_sitter

# 10) Ensure the dynamic linker can find the .so files.
# Tree-sitter docs indicate that for dynamic linking the library must be discoverable
# via LD_LIBRARY_PATH or similar.
ENV LD_LIBRARY_PATH=/usr/local/lib

WORKDIR /app
