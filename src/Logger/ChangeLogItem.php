<?php

namespace Bolt\Logger;

use Silex\Application;

/**
 *
 *
 */
class ChangeLogItem implements \ArrayAccess
{
    /**
     * @var Silex\Application
     */
    private $app;

    private $id;
    private $date;
    private $title;
    private $username;
    private $contenttype;
    private $contentid;
    private $mutation_type_raw;
    private $diff;
    private $comment;
    private $changedfields;

    public function __construct(Application $app, $values = array())
    {
        $this->app = $app;
        $this->setParameters($values);
    }

    public function __isset($key)
    {
        if (in_array($key, array('mutation_type', 'changedfields'))) {
            return true;
        }

        return false;
    }

    /**
     * Magic getter
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        if ($key == 'mutation_type') {
//             return $this->getEffectiveMutationType();
        } elseif ($key == 'changedfields') {
            $this->changedfields = $this->getChangedFields();
            return $this->changedfields;
        }
    }

    /**
     * ArrayAccess support
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * ArrayAccess support
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * ArrayAccess support
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * ArrayAccess support
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * Return a human valid mutation type
     *
     * @param  string       $mutation_type
     * @return array|string
     */
    private function getEffectiveMutationType()
    {
        switch ($this->mutation_type_raw) {
            case 'INSERT':
            case 'DELETE':
            default:
                return $this->mutation_type_raw;

            case 'UPDATE':
                $diff = $this->getParsedDiff();
                if (isset($diff['status'])) {
                    switch ($diff['status'][1]) {
                        case 'published':
                            return 'PUBLISH';

                        case 'draft':
                            return 'DRAFT';

                        case 'held':
                            return 'HOLD';

                        default:
                            return 'UPDATE';
                    }
                } else {
                    return 'UPDATE';
                }
        }
    }

    /**
     * Decode JSON and return an array
     *
     * @return array
     */
    private function getParsedDiff()
    {
        $pdiff = json_decode($this->diff_raw, true);

        foreach ($pdiff as $key => $value) {
            if (!isset($this->fields[$key])) {
                continue;
            }

            if ($this->fields[$key]['type'] == 'text' ||
                $this->fields[$key]['type'] == 'text' ||
                $this->fields[$key]['type'] == 'text' ||
                $this->fields[$key]['type'] == 'text' ||
                $this->fields[$key]['type'] == 'text') {
                    //
            }
        }

        return $pdiff;
    }

    /**
     * Set class parameters
     *
     * @param array $values
     */
    private function setParameters(array $values)
    {
        if (isset($values['id'])) {
            $this->id = $values['id'];
        }
        if (isset($values['date'])) {
            $this->date = $values['date'];
        }
        if (isset($values['title'])) {
            $this->title = $values['title'];
        }
        if (isset($values['username'])) {
            $this->username = $values['username'];
        }
        if (isset($values['ownerid'])) {
            $this->ownerid = $values['ownerid'];
            $user = $this->app['users']->getUser($values['ownerid']);

            if (isset($user['displayname'])) {
                $this->username = $user['displayname'];
            } elseif (isset($user['username'])) {
                $this->username = $user['username'];
            } else {
                $this->username = "(deleted user #" . $values['ownerid'] . ")";
            }
        }
        if (isset($values['contenttype'])) {
            $this->contenttype = $values['contenttype'];
        }
        if (isset($values['contentid'])) {
            $this->contentid = $values['contentid'];
        }
        if (isset($values['mutation_type'])) {
            $this->mutation_type_raw = $values['mutation_type'];
        }
        if (isset($values['diff'])) {
            $this->diff = $values['diff'];
        }
        if (isset($values['comment'])) {
            $this->comment = $values['comment'];
        }
    }

    /**
     *
     * @return array
     */
    private function getChangedFields()
    {
        $changedfields = array();
        $changes = json_decode($this->diff, true);

        if (empty($changes)) {
            return $changedfields;
        }

        // Get the contenttype that we're dealing with
        $contenttype = $this->app['storage']->getContentType($this->contenttype);
        $fields = $contenttype['fields'];

        //
        foreach ($changes as $key => $value) {
            $changedfields[$key] = array(
                'type'   => 'normal',
                'label'  => empty($fields[$key]['label']) ? $key : $fields[$key]['label'],
                'before' => array(
                    'raw'    => $value[0],
                    'render' => $value[0]
                ),
                'after'  => array(
                    'raw'    => $value[1],
                    'render' => $value[1]
                )
            );

            switch ($fields[$key]['type']) {
                case 'text':
                case 'slug':
                case 'textarea':
                case 'markdown':
                case 'text':
                default:
                    break;

                case 'html':
                    $changedfields[$key]['type'] = 'html';

                    break;

                case 'filelist':
                    $changedfields[$key]['type'] = 'filelist';
                    $before = json_decode($value[0], true);
                    $after  = json_decode($value[1], true);


                    $changedfields[$key]['before']['render'] = $before;
                    $changedfields[$key]['after']['render'] = $after;

                    break;

                case 'geolocation':
                    $changedfields[$key]['type'] = 'geolocation';
                    $before = json_decode($value[0], true);
                    $after  = json_decode($value[1], true);

                    $changedfields[$key]['before']['render'] = array(
                        'address'           => $before['address'],
                        'latitude'          => $before['latitude'],
                        'longitude'         => $before['longitude'],
                        'formatted_address' => $before['formatted_address']
                    );

                    $changedfields[$key]['after']['render'] = array(
                        'address'           => $after['address'],
                        'latitude'          => $after['latitude'],
                        'longitude'         => $after['longitude'],
                        'formatted_address' => $after['formatted_address']
                    );

                    break;

                case 'image':
                    $changedfields[$key]['type'] = 'image';

                    $before = json_decode($value[0], true);
                    $after  = json_decode($value[1], true);

                    $changedfields[$key]['before']['render'] = array(
                        'file'  => $before['file'],
                        'title' => $before['title']
                    );
                    $changedfields[$key]['after']['render'] = array(
                        'file'  => $after['file'],
                        'title' => $after['title']
                    );

                    break;

                case 'imagelist':
                    $changedfields[$key]['type'] = 'imagelist';
                    $before = json_decode($value[0], true);
                    $after  = json_decode($value[1], true);

                    $changedfields[$key]['before']['render'] = $before;
                    $changedfields[$key]['after']['render'] = $after;

                    break;

                case 'select':
                    $changedfields[$key]['type'] = 'select';

                    if (isset($fields[$key]['multiple']) && $fields[$key]['multiple']) {
                        $before = json_decode($value[0], true);
                        $after  = json_decode($value[1], true);
                    } else {
                        $before = $value[0];
                        $after  = $value[1];
                    }

                    $changedfields[$key]['before']['render'] = $before;
                    $changedfields[$key]['after']['render'] = $after;

                    break;

                case 'video':
                    $changedfields[$key]['type'] = 'video';
                    $before = json_decode($value[0], true);
                    $after  = json_decode($value[1], true);

                    $changedfields[$key]['before']['render'] = array(
                        'url'       => $before['url'],
                        'title'     => $before['title'],
                        'width'     => $before['width'],
                        'height'    => $before['height'],
                        'html'      => $before['html'],
                        'thumbnail' => $before['thumbnail']
                    );

                    $changedfields[$key]['after']['render'] = array(
                        'url'       => $after['url'],
                        'title'     => $after['title'],
                        'width'     => $after['width'],
                        'height'    => $after['height'],
                        'html'      => $after['html'],
                        'thumbnail' => $after['thumbnail']
                    );

                    break;
            }
        }

        return $changedfields;
    }
}
