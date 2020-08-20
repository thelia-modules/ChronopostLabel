# ChronopostLabel

Allows you to create labels for the modules ChronopostPickupPoint and ChronopostHomeDelivery.


## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is ChronopostLabel.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/chronopost-label-module:~1.0
```

## Usage

First, go to your back office, tab Modules, and activate the module ChronopostLabel.
Then go to ChronopostLabel configuration page and fill the required fields.

You can then generate and download labels from the Chronopost Label page accessible from the toolbar on the left of the BackOffice, or directly from the order page.


## Loop

###[chronopost.label.check.rights]

Check if label directory is writable and readable.

### Input arguments

None

### Output arguments

|Variable   |Description |
|---        |--- |
|$ERRMES    | Error message |
|$ERRFILE   | Folder where the error has been detected |

###[chronopost.label.export.home.delivery.labels]

Get a list of orders made with the home delivery module, and their label informations

### Input arguments

|Argument |Description |
|---      |--- |
|**order_ref** | Reference of the order you want to display|
|**order_id** | Id of the order you want to display|
|**delivery_type** | Delivery type (ex Fresh13) of the orders you want to display |
|**delivery_code** | Delivery code (ex 2R) of the orders you want to display. |
|**label_number** | Label number of the order you want to display |
|**label_directory** | Label directory of the orders you want to display |
|**order_status** | Status of the order you want to display|

### Output arguments

|Variable   |Description |
|---        |--- |
|$REFERENCE    | Reference of the order |
|$DELIVERY_TYPE    | Delivery type of the order (ex Fresh13) |
|$DELIVERY_CODE    | Delivery code of the order (ex 2R) |
|$LABEL_NBR    | Label number of the order |
|$LABEL_DIR    | Label directory of the order |
|$ORDER_ID    | Order ID |

###[chronopost.label.export.pickup.point.labels]

Get a list of orders made with the pickup point delivery module, and their label informations


### Input arguments

|Argument |Description |
|---      |--- |
|**order_ref** | Reference of the order you want to display|
|**order_id** | Id of the order you want to display|
|**delivery_type** | Delivery type (ex Fresh13) of the orders you want to display |
|**delivery_code** | Delivery code (ex 2R) of the orders you want to display. |
|**label_number** | Label number of the order you want to display |
|**label_directory** | Label directory of the orders you want to display |
|**order_status** | Status of the order you want to display|

### Output arguments

|Variable   |Description |
|---        |--- |
|$REFERENCE    | Reference of the order |
|$DELIVERY_TYPE    | Delivery type of the order (ex Fresh13) |
|$DELIVERY_CODE    | Delivery code of the order (ex 2R) |
|$LABEL_NBR    | Label number of the order |
|$LABEL_DIR    | Label directory of the order |
|$ORDER_ID    | Order ID |
