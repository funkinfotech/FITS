<?php

return [

    /*
     * Disk + folder for stored attachments. The disk MUST be private (not
     * web-served); files are only reachable through AttachmentController, which
     * runs an authorization check first.
     */
    'disk' => env('ATTACHMENTS_DISK', 'local'),
    'path' => 'attachments',

    'max_files' => (int) env('ATTACHMENTS_MAX_FILES', 10),
    'max_size_kb' => (int) env('ATTACHMENTS_MAX_SIZE_KB', 20480), // 20 MB

    /*
     * Reject images whose pixel count exceeds this before we try to decode them
     * (decompression-bomb guard). 40 MP ~= a 7300x5500 photo.
     */
    'image_max_pixels' => 40_000_000,

    /*
     * extension => the sniffed MIME types that are acceptable for it. The upload
     * must satisfy BOTH: an allowed extension, and content that finfo reports as
     * one of these types. OOXML/zip-container formats legitimately sniff as
     * application/zip.
     */
    'allowed' => [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'log'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ],

    /*
     * Raster images that get fully decoded and re-encoded through GD, which
     * strips metadata (EXIF/GPS), normalises the container and destroys any
     * appended payload / polyglot. Anything here that fails to decode as an
     * image is rejected.
     */
    'reencode' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],

    /*
     * Hard deny-list. These never pass regardless of the allow-list above,
     * matched against every "." segment of the filename.
     */
    'blocked_extensions' => [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'pgif', 'inc',
        'htaccess', 'htpasswd', 'user.ini', 'ini',
        'svg', 'svgz', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'xsl',
        'js', 'mjs', 'cjs', 'jsp', 'asp', 'aspx', 'cer', 'rb', 'py', 'pl', 'cgi',
        'sh', 'bash', 'zsh', 'ps1', 'psm1',
        'exe', 'bat', 'cmd', 'com', 'msi', 'msix', 'scr', 'dll', 'so', 'dylib',
        'jar', 'war', 'app', 'dmg', 'pkg', 'deb', 'rpm', 'apk', 'bin', 'run',
        'lnk', 'reg', 'vbs', 'vbe', 'wsf', 'hta', 'chm',
    ],
];
