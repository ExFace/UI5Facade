# O2Data Server Adapter

The O2Data server adapter transforms, based on the action that should be performed (read, create, update, delete or function imports), the give parameters in that way, that the OData 2 services can handle them.

To do so the server adapter evaluates what action should be performened, creates a new `sap.ui.model.odata.v2.ODataModel` and transforms the given paramaters based on the attributes data source data types.
The server adapter adds the success and error handlers for the server response to the `ODataModel` and calls the corresponding `ODataModel` method to the wanted action. Those methods are:

- `oDataModel.create`
- `oDataModel.read`
- `oDataModel.update`
- `oDataModel.remove`
- `oDataModel.callFunction`

## Filter comparators

The adapter accepts the canonical ExFace comparators emitted by DataTable filters. Atomic comparators are mapped to their corresponding OData filter operators. `BETWEEN` (`..`) is mapped to `BT`; a range with only its lower or upper bound is mapped to `GE` or `LE`. `IN` (`[`) is expanded into an OR group of `EQ` filters, while `NOT_IN` (`![`) becomes an AND group of `NE` filters.

It is configurable if multiple `CREATE, READ, UPDATE, DELETE` actions should be send to the server as one request, stacked by a batch, or if each action should be send as an own request. The default implementation of CRUD operations in OData2 services does not support multiple action calls stacked by a batch in one server request. Therefore by default the usage of batch requests is disabled.

To enable batch requests go to the UI5 Facade configuration json file (`exface.UI5Facade.config.json`) and change the following options:

- `"WEBAPP_EXPORT.ODATA.USE_BATCH_DELETES"` - `true` to enable batch for `DELETE` actions, default `false`
- `"WEBAPP_EXPORT.ODATA.USE_BATCH_WRITES"` - `true` to enable batch for `CREATE / UPDATE` actions, default `false`
- `"WEBAPP_EXPORT.ODATA.USE_BATCH_FUNCTION_IMPORTS"` - `true` to enable batch for `FUNCTION IMPORTS` actions, default `false`


