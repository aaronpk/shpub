<?php
namespace shpub;

/**
 * Abstract command class that handles generic properties
 */
class Command_AbstractProps
{
    /**
     * @var Config
     */
    protected $cfg;

    public function __construct($cfg)
    {
        $this->cfg = $cfg;
    }

    public static function optsGeneric(\Console_CommandLine_Command $cmd)
    {
        $cmd->addOption(
            'categories',
            array(
                'short_name'  => '-c',
                'long_name'   => '--category',
                'description' => 'Category names',
                'help_name'   => 'CAT',
                'action'      => 'StoreArray',
                'default'     => [],
            )
        );
        $cmd->addOption(
            'files',
            array(
                'short_name'  => '-f',
                'long_name'   => '--file',
                'description' => 'Files or URLs to upload',
                'help_name'   => 'PATH',
                'action'      => 'StoreArray',
                'default'     => [],
            )
        );
        $cmd->addOption(
            'direct_upload',
            array(
                'long_name'   => '--direct-upload',
                'description' => 'Ignore media endpoint at file upload',
                'action'      => 'StoreTrue',
                'default'     => false,
            )
        );
        $cmd->addOption(
            'name',
            array(
                'short_name'  => '-n',
                'long_name'   => '--name',
                'description' => 'Post title',
                'help_name'   => 'TITLE',
                'action'      => 'StoreString',
                'default'     => null,
            )
        );
        $cmd->addOption(
            'published',
            array(
                'long_name'   => '--published',
                'description' => 'Publish date',
                'help_name'   => 'DATE',
                'action'      => 'StoreString',
                'default'     => null,
            )
        );
        $cmd->addOption(
            'updated',
            array(
                'long_name'   => '--updated',
                'description' => 'Update date',
                'help_name'   => 'DATE',
                'action'      => 'StoreString',
                'default'     => null,
            )
        );
        $cmd->addOption(
            'slug',
            array(
                'short_name'  => '-s',
                'long_name'   => '--slug',
                'description' => 'URL path',
                'help_name'   => 'PATH',
                'action'      => 'StoreString',
                'default'     => null,
            )
        );
        $cmd->addOption(
            'syndication',
            array(
                'short_name'  => '-s',
                'long_name'   => '--syndication',
                'description' => 'Syndication URL(s)',
                'help_name'   => 'URL',
                'action'      => 'StoreArray',
                'default'     => [],
            )
        );
        $cmd->addOption(
            'x',
            array(
                'short_name'  => '-x',
                'long_name'   => '--xprop',
                'description' => 'Additional property',
                'help_name'   => 'key=value',
                'action'      => 'StoreArray',
                'default'     => [],
            )
        );
        static::addOptJson($cmd);
    }

    protected static function addOptHtml(\Console_CommandLine_Command $cmd)
    {
        $cmd->addOption(
            'html',
            array(
                'short_name'  => '-h',
                'long_name'   => '--html',
                'description' => 'Text content is HTML',
                'action'      => 'StoreTrue',
                'default'     => false,
            )
        );
    }

    protected static function addOptJson(\Console_CommandLine_Command $cmd)
    {
        $cmd->addOption(
            'json',
            array(
                'long_name'   => '--json',
                'description' => 'Send request data as JSON',
                'action'      => 'StoreTrue',
                'default'     => false,
            )
        );
    }

    protected function handleGenericOptions(
        \Console_CommandLine_Result $cmdRes, Request $req
    ) {
        $this->handleOptJson($cmdRes, $req);

        if ($cmdRes->options['published'] !== null) {
            $req->req->addPostParameter(
                'published', $cmdRes->options['published']
            );
        }
        if ($cmdRes->options['updated'] !== null) {
            $req->req->addPostParameter(
                'updated', $cmdRes->options['updated']
            );
        }
        if (count($cmdRes->options['categories'])) {
            $req->addPostParameter(
                'category', $cmdRes->options['categories']
            );
        }
        if ($cmdRes->options['name'] !== null) {
            $req->req->addPostParameter(
                'name', $cmdRes->options['name']
            );
        }
        if ($cmdRes->options['slug'] !== null) {
            $req->req->addPostParameter(
                'slug', $cmdRes->options['slug']
            );
        }
        if (count($cmdRes->options['syndication'])) {
            $req->addPostParameter(
                'syndication', $cmdRes->options['syndication']
            );
        }

        $req->setDirectUpload($cmdRes->options['direct_upload']);
        $this->handleFiles($cmdRes, $req);

        if (count($cmdRes->options['x'])) {
            $postParams = [];
            foreach ($cmdRes->options['x'] as $xproperty) {
                list($propkey, $propval) = explode('=', $xproperty, 2);
                if (!isset($postParams[$propkey])) {
                    $postParams[$propkey] = [];
                }
                $postParams[$propkey][] = $propval;
            }
            foreach ($postParams as $propkey => $propvals) {
                $req->addPostParameter($propkey, $propvals);
            }
        }
    }

    protected function handleOptJson(
        \Console_CommandLine_Result $cmdRes, Request $req
    ) {
        $req->setSendAsJson($cmdRes->options['json']);
    }

    protected function handleFiles(
        \Console_CommandLine_Result $cmdRes, Request $req
    ) {
        $files = $cmdRes->options['files'];
        $fileList = $urlList = [
            'audio' => [],
            'image' => [],
            'video' => [],
        ];

        foreach ($files as $filePath) {
            if (strpos($filePath, '://') !== false) {
                //url
                $mte      = new \MIME_Type_Extension();
                $mimetype = $mte->getMIMEType($filePath);
                $media    = \MIME_Type::getMedia($mimetype);
                if (!isset($urlList[$media])) {
                    Log::err('File type not allowed: ' . $mimetype);
                    exit(20);
                }
                $urlList[$media][] = $filePath;
            } else if (file_exists($filePath)) {
                //file
                $mimetype = \MIME_Type::autoDetect($filePath);
                $media    = \MIME_Type::getMedia($mimetype);
                if (!isset($urlList[$media])) {
                    Log::err('File type not allowed: ' . $mimetype);
                    exit(20);
                }
                $fileList[$media][] = $filePath;
            } else {
                Log::err('File does not exist: ' . $filePath);
                exit(20);
            }
        }
        foreach ($urlList as $type => $urls) {
            if ($type == 'image') {
                $type = 'photo';
            }
            if (count($urls) > 0) {
                $req->addProperty($type, $urls);
            }
        }
        foreach ($fileList as $type => $filePaths) {
            if ($type == 'image') {
                $type = 'photo';
            }
            if (count($filePaths) > 0) {
                $req->addUpload($type, $filePaths);
            }
        }
    }
}
?>
