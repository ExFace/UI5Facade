# Value-help for input widgets

The value-help feature of Fiori is easy to implement in the UI5 facade. In fact, in many cases, the value-help is added automatically. Here are the most common scenarios:

## Value-help for relations

If you use a widget like `InputCombo` or `InputComboTable` for an attribute, that is a relation to another object, the widget will automatically get a value-help dropdwn and autosuggest based on the `LABEL` attribute of the target (right) object of the relation. To achieve the best results, follow the general [recommendations for relation-widgets](https://github.com/ExFace/Core/blob/0.x-dev/Docs/Widgets/Forms_and_inputs/InputCombo.md).

## Custom value help

TODO