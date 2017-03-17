<?php

namespace Netcore\GAlerts;

use Exception;
use Illuminate\Support\Collection;

class Manager extends ManagerBase
{

    /**
     * Get list of all alerts
     *
     * @return Collection
     */
    public function all()
    {
        $data = $this->getDataStack();
        $rawData = array_get($data, '1.1', []);
        $alerts = collect();

        foreach ($rawData as $alert) {

            $fluentAlert = new GAlert([
                'query'     => $alert[2][3][1],
                'id'        => $alert[2][6][0][11],
                'dataId'    => $alert[1],
                'dataId2'   => end($alert[2][6][0]),
                'feedUrl'   => self::BASE_URL . '/feeds/' . end($alert) . '/' . $alert[2][6][0][11],
                'domain'    => $alert[2][3][2],
                'language'  => $alert[2][3][3][1],
                'region'    => end($alert[2][3]) ?: $alert[2][3][3][2],
                'howOften'  => $alert[2][6][0][4],
                'howMany'   => $alert[2][5],
                'deliverTo' => $alert[2][6][0][1],
            ]);

            $alerts->push($fluentAlert);
        }

        return $alerts;
    }

    /**
     * Delete alert with given ID
     *
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        $token = $this->getToken('x');
        $deleteUrl = self::DELETE_ENDPOINT . http_build_query(['x' => $token]);

        $params = [
            'params' => json_encode([null, (string) $id]),
        ];

        $deleteResponse = $this->resource->post($deleteUrl, [
            'form_params' => $params,
            'headers'     => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                'Origin'       => self::GOOGLE_URL,
                'Referer'      => self::BASE_URL,
                'User-Agent'   => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)',
            ],
        ]);

        $deleteResponseContent = $deleteResponse->getBody()->getContents();

        return (isset($deleteResponseContent[3]) && is_string($deleteResponseContent[3]));
    }

    /**
     * POST to create endpoint
     *
     * @param \Netcore\GAlerts\GAlert $alert
     * @return string
     */
    public function create(GAlert $alert)
    {
        $token = $this->getToken('x');
        $createUrl = self::CREATE_ENDPOINT . http_build_query(['x' => $token, 'hl' => 'en']);

        $formData = $this->buildParams($alert, 'create');

        $response = $this->resource->post($createUrl, [
            'form_params' => [
                'params' => $formData,
            ],
            'headers'     => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                'Origin'       => 'https://www.google.com',
                'Referer'      => 'https://www.google.com/alerts?hl=en',
                'User-Agent'   => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)',
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Build query string
     *
     * @param GAlert $alert
     * @param string $buildFor
     * @return array
     * @internal param string $action
     */
    private function buildParams(GAlert $alert, $buildFor = 'create')
    {
        $query = $alert->get('query');
        $deliverTo = $alert->get('deliverTo', GAlert::DELIVERY_EMAIL);
        $howOften = $alert->get('howOften', GAlert::FREQUENCY_DAILY);
        $language = $alert->get('language', 'en');
        $region = $alert->get('region', 'US');
        $howMany = $alert->get('howMany', GAlert::QUANTITY_ALL);

        $id = $alert->get('id');
        $dataId1 = $alert->get('dataId');
        $dataId2 = $alert->get('dataId2', $this->getToken('y'));

        $customLanguage = $alert->get('customLanguage', 0); // 0 == any lang, 1 == specified lang

        if ($language !== 'en') {
            $customLanguage = 1;
        }

        // Build delivery and frequency

        $deliveryAndFrequency = '';

        if ($deliverTo == GAlert::DELIVERY_FEED) {
            $deliveryAndFrequency = GAlert::DELIVERY_FEED . ',"",[],' . GAlert::FREQUENCY_AS_IT_HAPPENS;
        } else {
            $email = config('galerts.user');

            if ($howOften == GAlert::FREQUENCY_DAILY) {
                $deliveryAndFrequency = $deliverTo . ',"' . $email . '",[null,null,11],' . $howOften;
            }

            if ($howOften == GAlert::FREQUENCY_WEEKLY) {
                $deliveryAndFrequency = $deliverTo . ',"' . $email . '",[null,null,11, 1],' . $howOften;
            }

            if ($howOften == GAlert::FREQUENCY_AS_IT_HAPPENS) {
                $deliveryAndFrequency = $deliverTo . ',"' . $email . '",[],' . $howOften;
            }
        }

        // Source types
        $sources = 'null';

        // Where to watch alerts
        $anywhere = true;

        if (!$region || $region == GAlert::REGION_ANYWHERE) {
            $region = 'US';
        } else {
            $anywhere = false;
            $region = strtoupper(str_limit($region, 2, ''));
        }

        // Finally build the form data

        $formQuery = '';

        if ($buildFor == 'create') {
            $formQuery = '[null,[null,null,null,[null,"::query::","::domain::",[null,"::language::","::region::"],null,null,null,::anywhere::,::customLanguage::],::sources::,::quantity::,[[null,::deliveryAndFrequency::,"::langString::",null,null,null,null,null,"0",null,null,"::dataID2::"]]]]';
        }

        if ($buildFor == 'delete') {
            $formQuery = '[null,"::dataID1::"]';
        }

        $replacements = [
            'query'                => $query,
            'domain'               => 'com',
            'language'             => $language,
            'region'               => $region,
            'anywhere'             => $anywhere,
            'sources'              => $sources,
            'quantity'             => $howMany,
            'deliveryAndFrequency' => $deliveryAndFrequency,
            'langString'           => strtolower($language) . '-' . strtoupper($region),
            'dataID2'              => $dataId2,
            'dataID1'              => $dataId1,
            'id'                   => $id,
            'customLanguage'       => $customLanguage,
        ];

        foreach ($replacements as $key => $replacement) {

            if (is_bool($replacement)) {
                $replacement = $replacement ? '1' : '0';
            }

            $formQuery = str_replace('::' . $key . '::', $replacement, $formQuery);
        }

        return $formQuery;
    }

    /**
     * Get form token
     *
     * @param string $type [x,y]
     * @return string
     * @throws \Exception
     */
    private function getToken($type)
    {
        $data = $this->getDataStack();

        $tokens = [
            'x' => $data[3],
            'y' => $data[2][6][0][14],
        ];

        return $tokens[ $type ];
    }

    /**
     * Get window.STATE data containing tokens, alerts etc..
     *
     * @param bool $refresh
     * @return array
     * @throws \Exception
     */
    private function getDataStack($refresh = false)
    {
        static $stack;

        if ($refresh || (!$refresh && !$stack)) {
            $response = $this->resource->get(static::BASE_URL);
            $responseBody = $response->getBody()->getContents();

            // Extract window.STATE data
            $start = strpos($responseBody, 'window.STATE = ') + strlen('window.STATE = ');
            $end = strpos($responseBody, ';', $start + 1);

            $stack = json_decode(substr($responseBody, $start, $end - $start));

            if (!$stack) {
                throw new Exception('Unable to fetch window.STATE data from Google!');
            }
        }

        return $stack;
    }

    /**
     * Refresh data
     *
     * @return \Netcore\GAlerts\Manager
     */
    public function refreshData()
    {
        $this->authorize();
        $this->getDataStack(true);

        return $this;
    }

}