CREATE TABLE sys_file
(
    compressed     tinyint(1) DEFAULT '0' NOT NULL,
    compress_error text,
    compress_info  varchar(255) DEFAULT '' NOT NULL,
    KEY idx_compressed (compressed)
);

CREATE TABLE sys_file_processedfile
(
    compressed     tinyint(1) DEFAULT '0' NOT NULL,
    compress_error text,
    KEY idx_compressed (compressed)
);
