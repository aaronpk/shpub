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
                'long_name'   => '--files',
                'description' => 'Files or URLs to upload',
                'help_name'   => 'PATH',
                'action'      => 'StoreArray',
                'default'     => [],
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
    }

    protected function handleGenericOptions(
        \Console_CommandLine_Result $cmdRes, Request $req
    ) {
        if ($cmdRes->options['published'] !== null) {
            $req->req->addPostParameter(
                'published', $cmdRes->options['published']
            );
        }
        if (count($cmdRes->options['categories'])) {
            $req->addPostParameter(
                'category', $cmdRes->options['categories']
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

        $this->handleFiles($cmdRes, $req);

        if (count($cmdRes->options['x'])) {
            $postParams = [];
            foreach ($cmdRes->options['x'] as $xproperty) {
                list($propkey, $propval) = explode('=', $xproperty, 2);
                if (!isset($postParams[$propkey] )) {
                    $postParams[$propkey] = [];
                }
                $postParams[$propkey][] = $propval;
            }
            foreach ($postParams as $propkey => $propvals) {
                $req->addPostParameter($propkey, $propvals);
            }
        }
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
            if (count($urls) == 1) {
                $req->req->addPostParameter($type, reset($urls));
            } else if (count($urls) > 1) {
                $n = 0;
                foreach ($urls as $url) {
                    $req->req->addPostParameter(
                        $type . '[' . $n++ . ']', $url
                    );
                }
            }
        }
        foreach ($fileList as $type => $filePaths) {
            if ($type == 'image') {
                $type = 'photo';
            }
            if (count($filePaths) == 1) {
                $req->addUpload($type, reset($filePaths));
            } else if (count($filePaths) > 0) {
                $req->addUpload($type, $filePaths);
            }
        }
    }
}
?>
