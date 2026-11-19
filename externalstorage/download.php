
<?php
require_once(__DIR__ . '/../../../../config.php');

$submissionid = required_param('submissionid', PARAM_INT);

// Get assignment submission.
$submission = $DB->get_record(
    'assign_submission',
    ['id' => $submissionid],
    '*',
    MUST_EXIST
);

// Get assignment.
$assignment = $DB->get_record(
    'assign',
    ['id' => $submission->assignment],
    '*',
    MUST_EXIST
);

// Get course module.
$cm = get_coursemodule_from_instance(
    'assign',
    $assignment->id,
    $assignment->course,
    false,
    MUST_EXIST
);

// Login and permissions.
require_login($assignment->course, false, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

// Student can download their own submission.
// Teachers with grading permission can download submissions.
if (!has_capability('mod/assign:grade', $context) &&
        (int)$submission->userid !== (int)$USER->id) {
    throw new required_capability_exception(
        $context,
        'mod/assign:view',
        'nopermissions',
        ''
    );
}

// Get external storage metadata.
$record = $DB->get_record(
    'assignsubmission_externalstorage',
    ['submission' => $submissionid],
    '*',
    MUST_EXIST
);

// Get storage root.
$storagepath = get_config(
    'assignsubmission_externalstorage',
    'storagepath'
);

if (empty($storagepath)) {
    throw new moodle_exception('filenotfound');
}

$realbase = realpath($storagepath);

if (!$realbase || !is_dir($realbase)) {
    throw new moodle_exception('filenotfound');
}

// Resolve the stored relative path.
$relativepath = ltrim($record->storagekey, '/\\');

$filepath = $realbase . DIRECTORY_SEPARATOR .
    str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativepath);

$realfile = realpath($filepath);

// Validate file location and accessibility.
if (!$realfile ||
        strpos($realfile, $realbase . DIRECTORY_SEPARATOR) !== 0 ||
        !is_file($realfile) ||
        !is_readable($realfile)) {
    throw new moodle_exception('filenotfound');
}

// Verify physical file size against database metadata.
if (filesize($realfile) !== (int)$record->filesize) {
    throw new moodle_exception('filenotfound');
}

// Determine MIME type.
$mimetype = !empty($record->mimetype)
    ? $record->mimetype
    : get_mimetype_for_sending($record->filename);

// Send the actual file from disk.
// Fifth argument MUST be false: $realfile is a filesystem path.
// Sixth argument true forces a safe download of student-submitted content.
// Clear accidental output before sending the binary file.
while (ob_get_level() > 0) {
    $handlerstack = ob_list_handlers();
    $activehandler = array_pop($handlerstack);

    if ($activehandler === 'default output handler') {
        ob_clean();
        break;
    }

    ob_end_flush();
}
// Identify file types that browsers can display inline.
$extension = strtolower(pathinfo($record->filename, PATHINFO_EXTENSION));

$inlineextensions = [
    'pdf',
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'txt',
    'mp3',
    'wav',
    'ogg',
    'mp4',
    'webm',
];

$openinline = in_array($extension, $inlineextensions, true);

// Send inline for PDF/images; force download for everything else.
send_file(
    $realfile,
    $record->filename,
    0,
    0,
    false,
    !$openinline,
    $mimetype
);
