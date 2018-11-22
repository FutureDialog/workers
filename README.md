# FutureDialog Worker 

## Pipes

- push_android 
    
    Android push notifications
    
- push_ios
    
    Ios APNS push notifications
    
- push_win

    Win WNS push notifications

- key_ios

    Pem keys for APNS

## Push Notifications 

**Send**
    
    $data = [
        'apiKey' => '',
        'recipients' => [
        ],
        'notification' => [
            'title' => '',
            'body' => '',
        ],
        'payload' => [
        ],
        'onFail' => 'fail_tube',
        'onSuccess' => 'success_tube',
        'onComplete' => 'complete_tube'
    ];
    
- apiKey

    *required | string or array(for WNS only)*
    
    FCM Api key or APNS Key name
    
    **For WNS apiKey must be and array:**
    
        $apiKey = [
            'client_id' => '',
            'client_sectet' => ''
        ]
    
- recipients
    
    *required | array*
    
    Array of recipients. 
    
    Array of device tokens: Ex:
    
        [
            'devide_token',
            'devide_token',
            'devide_token',
            ...
        ];
    
    Or array of recipients with your optional data. Data will be returned in callback pipe as is.
    
        [
            [
                'token' => 'devide_token', // Required property
                // Your optional data you want to receive with callback 
                'recipient_id' => '1',
                'recipient_data' => '[],
                ...
            ],
            [
                'token' => 'devide_token', // Required property
                // Your optional data you want to receive with callback 
                'recipient_id' => '2',
                'recipient_data' => '[],
                ...
            ],
            ...
        ];
        
- notification

    - title 
    
        *required | string*
        
        Title of the push notification
        
    - body
    
        *required | string*
        
        Body of the push notification
        
    - icon
    
        *optional | string | Android only*
        
        The name of your drawable resource as string
    
    - color
    
        *optional | string | in #rrggbb format | Android only*
    
        Background color of the notification icon when showing details on notifications
    
    - badge 
    
        *optional | int*
        
        Add number of notifications to your apps icon
     
    - sound
        
        *optional | string | IOS only*
        
        Set the sound to play.
        
    - type
        
        *required for WNS only | string*
        
        One of the following:
        - raw
        - badge
        - tile | toast
     
- payload

    *optional | array*
    
    Data that will be send with push notification
    
- onFail

    *optional | string*

    Pipe to notify failed jobs. 
    
    Response example for array of recipient's tokens: 
        
        {
            "job_id":31,
            "time":1498549975,
            "count":1,
            "data":[
                {
                    "token":"device_token",
                    "status":false,
                    "error":"NotRegistered"
                }
            ]
        }
        
    Response example for array of recipients:
                
            {
                "job_id":42,
                "time":1498550263,
                "count":1,
                "data":[
                    {
                        "token":"0a39ae8b1d2c933fec9d8cb8dfb672905a275f0b7bc0ce6035829da3a72a2c03",
                        "status":false,
                        "error":"Error info",
                        // your optional data goes here
                        'recipient_id' => '1',
                        'recipient_data' => '[],
                    }
                ]
            }


- onSuccess

    *optional | string* 

    Pipe to notify successfull jobs
    
    Response example for array of recipient's tokens:
    
        {
            "job_id":31,
            "time":1498549975,
            "count":1,
            "data":[
                {
                    "token":"device_token",
                    "status":true
                }
            ]
        }
        
    Response example for array of recipients:
            
            {
                "job_id":42,
                "time":1498550263,
                "count":1,
                "data":[
                    {
                        "token":"device_token",
                        "status":true
                        // your optional data goes here
                        'recipient_id' => '1',
                        'recipient_data' => '[],
                    }
                ]
            }

- onComplete

    *optional | string* 

    Pipe to notify completed jobs 
    
    Response example for array of recipient's tokens:
    
        {
            "job_id":31,
            "time":1498549975,
            "success":1,
            "failure":1,
            "data":[
                {
                    "token":"device_token",
                    "status":true
                },
                {
                    "token":"device_token",
                    "status":false,
                    "error":"NotRegistered"
                }
            ]
        }
        
    Response example for array of recipients:
        
        {
            "job_id":42,
            "time":1498550263,
            "success":1,
            "failure":1,
            "data":[
                {
                    "token":"device_token",
                    "status":true
                    // your optional data goes here
                    'recipient_id' => '1',
                    'recipient_data' => '[],
                },
                {
                    "token":"device_token",
                    "status":false,
                    "error":"Error info"
                    // your optional data goes here
                    'recipient_id' => '1',
                    'recipient_data' => '[],
                }
            ]
        }