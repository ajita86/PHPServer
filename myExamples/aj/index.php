<?php
require '../../src/WebSite.php';

use seekquarry\atto\WebSite;

$test = new WebSite();

$test->get('/images/{file_name}', function () use ($test) {
    $file_name = __DIR__ . "/images/" . urldecode($_REQUEST['file_name']);
    if (file_exists($file_name)) {
        $test->header("Content-Type: " . $test->mimeType($file_name));
        echo $test->fileGetContents($file_name);
    } else {
        $test->trigger("ERROR", "/404");
    }
}
);

$test->get('/bio', function() {
    require 'Bio.php';
});

$test->get('/blog', function() {
    require 'Blog.php';
});

$test->get('/proposal', function() {
    require 'CS297Proposal.html';
});

$test->get('/', function() {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Hello World - Atto Server</title></head>
    <body>
    <h1>Hello World!</h1>
    <?= __DIR__ ?>
    <div>Example server routes:</div>
    <div><a href="/bio">Bio</a></div>
    <div><a href="/blog">Blog</a></div>
    <div><a href="/proposal">Proposal</a></div>
    </body>
    </html>
<?php
});

if($test->isCli()) {
    /*
       This line is used if the app is run from the command line
       with a line like:
       php index.php
       It causes the server to run on port 8000
     */
    $test->listen(8000);
} else {
    /* This line is for when site is run under a web server like
       Apache, nginx, lighttpd, etc. This folder contains a .htaccess
       to redirect traffic through this index.php file. So redirects
       need to be on to use this example under a different web server.
     */
    $test->process();
}
