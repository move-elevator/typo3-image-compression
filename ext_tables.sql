CREATE TABLE sys_file
(
    compressed     tinyint(1) DEFAULT '0' NOT NULL,
    compress_error text,
    compress_info  varchar(255) DEFAULT '' NOT NULL
);

CREATE TABLE sys_file_processedfile
(
    compressed     tinyint(1) DEFAULT '0' NOT NULL,
    compress_error text
);
