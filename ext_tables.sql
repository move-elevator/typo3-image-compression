CREATE TABLE sys_file
(
    compressed     tinyint(1) DEFAULT '0' NOT NULL,
    compress_error text
);

CREATE TABLE sys_file_processedfile
(
    compressed     tinyint(1) DEFAULT '0' NOT NULL,
    compress_error text
);
