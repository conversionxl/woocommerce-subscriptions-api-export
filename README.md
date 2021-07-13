After clone, run `git checkout -b trunk` to get started.


Usage:

Export subscription order to ChartMogul.

[--subscription-id]
: Subscription ID.

[--order-id]
: Order ID.

[--customer-id]
: Customer ID.

[--product-id]
: Product ID.

[--all-subscriptions]
: To run script for all subscriptions.

[--all-orders]
: To run script for all orders.

[--date-time-modifier]
: A date/time string. Valid formats are explained in <a href="https://secure.php.net/manual/en/datetime.formats.php">Date and Time Formats</a>.

[--data-source]
: Run for specific data source, pass the datasource UUID from ChartMogul.

[--create-data-source]
: To create data source.

## EXAMPLES

     # Export single subscription to ChartMogul.
     $ wp cxl shop-export-to-chartmogul --subscription-id=25 --data-source=xxxxxx
     Subscription #25 sent to ChartMogul.

     # Export single subscription to ChartMogul.
     $ wp cxl shop-export-to-chartmogul --order-id=25 --data-source=xxxxxx
     Subscription ### sent to ChartMogul.

     # Export customer to ChartMogul.
     $ wp cxl shop-export-to-chartmogul --customer-id=25 --data-source=xxxxxx
     Customer ### sent to ChartMogul.

     # Export product as plan to ChartMogul.
     $ wp cxl shop-export-to-chartmogul --product-id=25 --data-source=xxxxxx
     Product ### sent to ChartMogul.

     # Export all subscriptions to ChartMogul.
     $ wp cxl shop-export-to-chartmogul --all-subscriptions --data-source=xxxxxx
     Subscription #25 sent to ChartMogul.

     # Export all orders to ChartMogul.
     $ wp cxl shop-export-to-chartmogul --all-orders --data-source=xxxxxx
     Order #25 sent to ChartMogul.

     # Create Datasource in ChartMogul.
     $ wp cxl shop-export-to-chartmogul --create-data-source=true --data-source=new-datasource
     Success: Data source created successfully.

## Options
	 $ wp option add cxl_cm_account_token 900asdfasdc019adkjasdf09808
	 $ wp option add cxl_cm_secret_key 900asdfasdc019adkjasdf09808
