After clone, run `git checkout -b trunk` to get started.


Usage:

Export subscription order to ChartMogul.

[--id]
: Subscription ID.

[--all]
: To run script for all subscriptions.

[--data-source]
: Run for specific data source, pass the datasource UUID from ChartMogul.

[--create-data-source]
: To create data source.

## EXAMPLES

    # Export single subscriptions to ChartMogul.
    $ wp cxl shop-subscription-export-chartmogul --id=25 --data-source=xxxxxx
    Subscription #25 sent to ChartMogul.

    # Export all subscriptions to ChartMogul.
    $ wp cxl shop-subscription-export-chartmogul --all --data-source=xxxxxx
    Subscription #25 sent to ChartMogul.

    # Create Datasource in ChartMogul.
    $ wp cxl shop-subscription-export-chartmogul --create-data-source=true --data-source=new-datasource
    Success: Data source created successfully.
