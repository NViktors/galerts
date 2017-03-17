<?php

namespace Netcore\GAlerts;

use Illuminate\Support\Collection;

class GAlert extends GAlertBase
{

    /**
     * Get all alerts
     *
     * @return Collection
     */
    public static function all()
    {
        return app(Manager::class)->all();
    }

    /**
     * Find alert by id
     *
     * @param $id
     * @return \Netcore\GAlerts\GAlert
     */
    public static function findById($id)
    {
        $alerts = app(Manager::class)->all();

        return $alerts->where('id', $id)->first();
    }

    /**
     * Create and return new instance
     *
     * @param array $data
     * @return \Netcore\GAlerts\GAlert
     */
    public static function create(array $data)
    {
        $alert = app(Manager::class)->create(new self($data));
        $id = array_get($alert, '4.0.1');

        // Dirty, but works..
        app(Manager::class)->refreshData();

        return self::findByDataId($id);
    }

    /**
     * Find by data id
     *
     * @param $id
     * @return mixed
     */
    public static function findByDataId($id)
    {
        $alerts = app(Manager::class)->all();

        return $alerts->where('dataId', $id)->first();
    }

    /**
     * Delete alert
     *
     * @param GAlert|string $entry
     * @return bool
     */
    public static function delete($entry)
    {
        if ($entry instanceof GAlert) {
            $entry = $entry->dataId;
        }

        return (bool) app(Manager::class)->delete($entry);
    }

}