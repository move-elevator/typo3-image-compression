CREATE TABLE sys_file
(
    compressed              tinyint(1)   DEFAULT '0' NOT NULL,
    compress_error          text,
    compress_provider       varchar(32)  DEFAULT '' NOT NULL,
    compress_tool           varchar(32)  DEFAULT '' NOT NULL,
    compress_original_size  int(11)      DEFAULT '0' NOT NULL,
    compress_size           int(11)      DEFAULT '0' NOT NULL,
    compress_tstamp         int(11)      DEFAULT '0' NOT NULL,
    KEY idx_compressed (compressed)
);

CREATE TABLE sys_file_processedfile
(
    compressed              tinyint(1)   DEFAULT '0' NOT NULL,
    compress_error          text,
    compress_provider       varchar(32)  DEFAULT '' NOT NULL,
    compress_tool           varchar(32)  DEFAULT '' NOT NULL,
    compress_original_size  int(11)      DEFAULT '0' NOT NULL,
    compress_size           int(11)      DEFAULT '0' NOT NULL,
    compress_tstamp         int(11)      DEFAULT '0' NOT NULL,
    KEY idx_compressed (compressed)
);
