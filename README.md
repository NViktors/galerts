# Google Alerts manager

### Available methods

```php

    use Netcore\GAlers\GAlert;
    
    // Get collection of existing alerts
    
    GAlert::all();
    
    // Create an alert
    // retuns an instance of GAlert
    
    $alert = GAlert::create([
        'query' => 'Alert query..',
        'frequency' => GAlert::FREQUENCY_DAILY /* or ::FREQUENCY_AS_IT_HAPPENS or ::FREQUENCY_WEEKLY */
        'deliverTo' => GAlert::DELIVERY_EMAIL /* or ::DELIVERY_RSS */,
        ....
    );
    
    // ::create returns an instance of our new alert
    
    // Delete an alert
    
    GAlert::delete($alert); /* You can pass instance itself */
    GAlert::delete('28764d5015595ee0:7d6ec6d0bb215180:com:lv:LV'); /* Or use dataId */

```

To be continued...