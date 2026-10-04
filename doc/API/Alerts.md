### `get_alert`

Get details of an alert

Route: `/api/v0/alerts/:id`

- id is the alert id, you can obtain a list of alert ids from [`list_alerts`](#list_alerts).

Input:

  -

Example:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts/1
```

Output:

```json
{
 "status": "ok",
 "message": "",
 "count": 7,
 "alerts": [
  {
   "hostname": "localhost",
   "id": "1",
   "device_id": "1",
   "rule_id": "1",
   "state": "1",
   "alerted": "1",
   "open": "1",
   "timestamp": "2014-12-11 14:40:02"
  }]
}
```

### `ack_alert`

Acknowledge an alert

Route: `/api/v0/alerts/:id`

- id is the alert id, you can obtain a list of alert ids from [`list_alerts`](#list_alerts).
- note is the note to add to the alert
- until_clear is a boolean. With the value false, the alert triggers
  again when it becomes worse, becomes better, or changes.

Input:

  -

Example:

```curl
curl -X PUT -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts/1
```

Output:

```json
{
 "status": "ok",
 "message": "",
 "message": "Alert has been acknowledged"
}
```

### `unmute_alert`

Unmute an alert

Route: `/api/v0/alerts/unmute/:id`

- id is the alert id, you can obtain a list of alert ids from [`list_alerts`](#list_alerts).

Input:

  -

Example:

```curl
curl -X PUT -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts/unmute/1
```

Output:

```json
{
 "status": "ok",
 "message": "Alert has been unmuted"
}
```

### `list_alerts`

List all alerts

Route: `/api/v0/alerts`

Input:

- state: Filter the alerts by state, 0 = ok, 1 = alert, 2 = ack
- severity: Filter the alerts by severity. Valid values are `ok`, `warning`, `critical`.
- alert_rule: Filter alerts by alert rule ID.
- order: How to order the output, default is by timestamp
  (descending). Can be appended by DESC or ASC to change the order.

Examples:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts?state=1
```

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts?severity=critical
```

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts?order=timestamp%20ASC
```

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alerts?alert_rule=49
```

Output:

```json
{
 "status": "ok",
 "message": "",
 "count": 1,
 "alerts": [
  {
   "id": "1",
   "device_id": "1",
   "rule_id": "1",
   "state": "1",
   "alerted": "1",
   "open": "1",
   "timestamp": "2014-12-11 14:40:02"
  }]
}
```

## Rules

### `get_alert_rule`

Get the alert rule details.

Route: `/api/v0/rules/:id`

- id is the rule id.

Input:

  -

Example:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/rules/1
```

Output:

```json
{
 "status": "ok",
 "message": "",
 "count": 1,
 "rules": [
  {
   "id": "1",
   "device_id": "1",
   "rule": "%devices.os != \"Juniper\"",
   "severity": "warning",
   "extra": "{\"invert\":false}",
   "default_operation_step_duration_seconds": 300,
   "alert_operation_id": 12,
   "operations": [
    {
      "id": 12,
      "name": "Default operation",
      "position": 0,
      "operation_phase": "problem",
      "escalation_step_from": 1,
      "escalation_step_to": 2,
      "start_in_seconds": 60,
      "step_duration_seconds": 0,
      "transports": [
       {"id": "3", "text": "Mail: NOC"},
       {"id": "g2", "text": "Group: On-call"}
      ]
    }
   ],
   "disabled": "0",
   "name": "A test rule"
  }
 ]
}
```

### `delete_rule`

Delete an alert rule by id

Route: `/api/v0/rules/:id`

- id is the rule id.

Input:

  -

Example:

```curl
curl -X DELETE -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/rules/1
```

Output:

```json
{
 "status": "ok",
 "message": "",
 "message": "Alert rule has been removed"
}
```

### `list_alert_rules`

List the alert rules.

Route: `/api/v0/rules`

  -

Input:

  -

Example:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/rules
```

Output:

```json
{
 "status": "ok",
 "message": "",
 "count": 1,
 "rules": [
  {
   "id": "1",
   "device_id": "-1",
   "rule": "%devices.os != \"Juniper\"",
   "severity": "critical",
   "extra": "{\"invert\":false}",
   "default_operation_step_duration_seconds": 300,
   "alert_operation_id": 12,
   "operations": [
    {
      "id": 12,
      "name": "Default operation",
      "position": 0,
      "operation_phase": "problem",
      "escalation_step_from": 1,
      "escalation_step_to": null,
      "start_in_seconds": 300,
      "step_duration_seconds": 0,
      "transports": [
       {"id": "3", "text": "Mail: NOC"}
      ]
    }
   ],
   "disabled": "0",
   "name": "A test rule"
  }]
}
```

### `add_rule`

Add a new alert rule.

Route: `/api/v0/rules`

  -

Input (JSON):

- devices: an array of device ids, or -1 for a global rule
- groups: Array of device group ids
- locations: Array of location ids
- invert_map: Optional boolean. When `true`, the rule applies to all
  devices except the selected devices, groups and locations.
- builder: the rule in the format `entity.condition value`. An example
  is `devices.status != 0` for the devices with a down mark. Encode it
  as JSON in the current storage format of the rules.
- severity: the severity level of the alert. The values are Ok, Warning, and Critical.
- disabled: the state of the rule. 0 is enabled and 1 is disabled
- default_operation_step_duration: Optional. When `alert_operation_id` is set, updates that **operation’s** default step duration (for example `5 m`). The value is stored on the global operation, not on the rule. When a segment’s `step_duration_seconds` is `0`, this default (or the global config default if unset) is used as the repeat interval.
- alert_operation_id: ID of a global alert operation (see **Alerts → Operations** in the UI), or `null` to suppress notifications for this rule
- operations: (optional) If `alert_operation_id` is not sent, a legacy array of operation objects is converted into a new global operation (with one **segment** per array element) and linked to the rule. Each segment has its own escalation range, timing, and transports.
  - operation_phase: `problem`, `recovery`, or `update`
  - escalation_step_from: 1-based escalation step start
  - escalation_step_to: escalation step end (`null` for no limit)
  - start_in_seconds: delay before first notification in this operation
  - step_duration_seconds: repeat interval in seconds (`0` means use the operation’s default step duration, or the global config default)
  - transports: required array of targets
    - single transport id: `3`
    - transport group id: `\"g2\"`
- invert: it inverts the check of the rule.
- name: the name of the rule. This field is mandatory.
- notes: your own notes about this rule
- proc: Optional. URL of a procedure/runbook for this rule, shown as a link on the alerts page and passed to transports. Must be `http://` or `https://` to be rendered, max 80 characters.

Example:

```curl
curl -X POST -d '{"devices":[1,2,3], "name":"testrule", "builder":{"condition":"AND","rules":[{"id":"devices.hostname","field":"devices.hostname","type":"string","input":"text","operator":"equal","value":"localhost"}],"valid":true}, "severity":"critical", "default_operation_step_duration":"5 m", "alert_operation_id": 12, "notes":"This a note from the API"}' -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/rules
```

Output:

```json
{
 "status": "ok"
}
```

### `edit_rule`

Edit an existing alert rule

Route: `/api/v0/rules`

  -

Input (JSON):

- rule_id: give the rule_id to edit an existing rule. Without this
  field, the API creates a new rule.
- devices: an array of device ids, or -1 for a global rule
- groups: Array of device group ids
- locations: Array of location ids
- invert_map: Optional boolean. When `true`, the rule applies to all
  devices except the selected devices, groups and locations.
- builder: the rule in the format `entity.condition value`. An example
  is `devices.status != 0` for the devices with a down mark. Encode it
  as JSON in the current storage format of the rules.
- severity: the severity level of the alert. The values are Ok, Warning, and Critical.
- disabled: the state of the rule. 0 is enabled and 1 is disabled
- default_operation_step_duration: Optional. When `alert_operation_id` is set, updates that **operation’s** default step duration (for example `5 m`). Stored on the operation, not the rule.
- alert_operation_id: ID of a global alert operation, or `null` to suppress notifications for this rule
- operations: (optional) Legacy array of operation objects; used only when `alert_operation_id` is not present in the request
  - operation_phase: `problem`, `recovery`, or `update`
  - escalation_step_from: 1-based escalation step start
  - escalation_step_to: escalation step end (`null` for no limit)
  - start_in_seconds: delay before first notification in this operation
  - step_duration_seconds: repeat interval in seconds (`0` means use the operation’s default step duration, or the global config default)
  - transports: required array of targets
    - single transport id: `3`
    - transport group id: `\"g2\"`
- invert: it inverts the check of the rule.
- name: the name of the rule. This field is mandatory.
- notes: your own notes about this rule
- proc: Optional. URL of a procedure/runbook for this rule, shown as a link on the alerts page and passed to transports. Must be `http://` or `https://` to be rendered, max 80 characters.

Example:

```curl
curl -X PUT -d '{"rule_id":1,"devices":["-1"], "name":"testrule", "builder":{"condition":"AND","rules":[{"id":"devices.hostname","field":"devices.hostname","type":"string","input":"text","operator":"equal","value":"localhost"}],"valid":true}, "severity":"critical", "default_operation_step_duration":"5 m", "alert_operation_id": 12, "notes":"This a note from the API"}' -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/rules
```

Output:

```json
{
 "status": "ok"
}
```

## Alert templates

### `get_alert_template`

Get the alert template details.

Route: `/api/v0/alert_templates/:id`



Input:

  - id: (Required) is the alert template id.

Example:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert_templates/1
```

Output:

```json
{
  "status": "ok",
  "alert_templates": [
    {
      "id": 4,
      "name": "Default Alert Template",
      "template": "{{ $alert->title }}\nSeverity: {{ $alert->severity }}\n@if ($alert->state == 0)Time elapsed: {{ $alert->elapsed }} @endif\nTimestamp: {{ $alert->timestamp }}\nUnique-ID: {{ $alert->uid }}\nRule: @if ($alert->name) {{ $alert->name }} @else {{ $alert->rule }} @endif\n@if ($alert->faults) Faults:\n@foreach ($alert->faults as $key => $value)\n  #{{ $key }}: {{ $value['string'] }}\n@endforeach\n@endif\nAlert sent to:\n@foreach ($alert->contacts as $key => $value)\n  {{ $value }} <{{ $key }}>\n@endforeach",
      "title": null,
      "title_rec": null,
      "alert_rules": []
    },
  ],
  "count": 1
}
```

### `list_alert_templates`

List the alert templates.

Route: `/api/v0/alert_templates`


Input: None

Example:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert_templates
```

Output:

```json
{
  "status": "ok",
  "alert_templates": [
    {
      "id": 4,
      "name": "Default Alert Template",
      "template": "{{ $alert->title }}\nSeverity: {{ $alert->severity }}\n@if ($alert->state == 0)Time elapsed: {{ $alert->elapsed }} @endif\nTimestamp: {{ $alert->timestamp }}\nUnique-ID: {{ $alert->uid }}\nRule: @if ($alert->name) {{ $alert->name }} @else {{ $alert->rule }} @endif\n@if ($alert->faults) Faults:\n@foreach ($alert->faults as $key => $value)\n  #{{ $key }}: {{ $value['string'] }}\n@endforeach\n@endif\nAlert sent to:\n@foreach ($alert->contacts as $key => $value)\n  {{ $value }} <{{ $key }}>\n@endforeach",
      "title": null,
      "title_rec": null,
      "alert_rules": []
    },
  ],
  "count": 1
}
```

### `add_alert_template`

Add a new alert template.

Route: `/api/v0/alert_templates`

Input (JSON):

- name: (Required) Name for the new template
- template: (Required) Template code used to generate the alert message
- title: Title that is used when an alert is generated
- title_rec: Title that is used when an alert has recovered
- alert_rules: an array of rule_id values for this template. See also
  [`list_alert_rules`](#list_alert_rules).

Example:

```curl
curl -X POST -d '{"name":"new alert template","template":"---","title":"CREATED ALERT","title_rec": "ALERT RECOVERED","alert_rules":[]}' -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert_templates
```

Output:
- status: Status of the request. Can be: ok, warning, error
- message: the output of this call. It also holds the error messages.
- id: The id of the newly created alert template

```json
{
  "status": "ok",
  "message": "Alert template has been created and attached rules have been updated.",
  "id": 2
}
```

### `edit_rule`

Edit an existing alert rule

Route: `/api/v0/alert_templates`

Input (JSON):

- name: (Required) Name for the new template
- template: (Required) Template code used to generate the alert message
- template_id: (Required) the id of the template to change. Without
  this field, the API creates a new alert template.
- title: Title that is used when an alert is generated
- title_rec: Title that is used when an alert has recovered
- alert_rules: an array of rule_id values for this template. See also
  [`list_alert_rules`](#list_alert_rules).

Example:

```curl
curl -X POST -d '{"name":"new alert template","template":"---","template_id":"2","title":"CREATED ALERT","title_rec": "ALERT RECOVERED","alert_rules":[]}' -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert_templates
```

Output:

```json
{
  "status": "ok",
  "message": "Alert template has been updated and attached rules have been updated.",
  "id": 2
}
```

### `list_alert_transports`

List the alert transports. Fields whose name contains `password`, `secret` or `token` are masked.

Route: `/api/v0/alert/transports`

Example:

```curl
curl -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert/transports
```

Output:

```json
{
    "status": "ok",
    "count": 1,
    "transports": [
        {
            "transport_id": 3,
            "transport_name": "Ops webhook",
            "transport_type": "api",
            "is_default": false,
            "transport_config": {
                "api-method": "POST",
                "api-url": "https://hooks.example/alerts",
                "api-as-form": true,
                "api-body": "alertID={{ $alert_id }}\nruleID={{ $rule_id }}",
                "api-auth-password": null
            }
        }
    ]
}
```

### `add_alert_transport`

Create an alert transport. The configuration is validated by the transport type, exactly as the web form does.

Route: `/api/v0/alert/transports`

Input (JSON):

- name: the transport name. Mandatory.
- type: the transport type, for example `api`, `mail` or `slack`. Mandatory.
- is_default: optional boolean.
- config: object with the transport's configuration fields (the field names of the web form, for example `api-url`). Mandatory.

Example:

```curl
curl -X POST -d '{"name":"Ops webhook","type":"api","config":{"api-method":"POST","api-url":"https://hooks.example/alerts","api-as-form":true,"api-body":"alertID={{ $alert_id }}\nruleID={{ $rule_id }}"}}' -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert/transports
```

Output: the created transport in the same format as `list_alert_transports`, with HTTP status 201.

### `delete_alert_transport`

Delete an alert transport and remove it from every operation segment.

Route: `/api/v0/alert/transports/:id`

```curl
curl -X DELETE -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert/transports/3
```

### `list_alert_operations`

List the global alert operations with their segments and transports.

Route: `/api/v0/alert/operations`

Output:

```json
{
    "status": "ok",
    "count": 1,
    "operations": [
        {
            "id": 12,
            "name": "Default operation",
            "default_operation_step_duration_seconds": 300,
            "notifications_suppressed": false,
            "segments": [
                {"id": 20, "position": 0, "operation_phase": "problem", "escalation_step_from": 1, "escalation_step_to": null, "start_in_seconds": 60, "step_duration_seconds": 0, "transports": [{"id": "3", "text": "Api: Ops webhook"}]}
            ]
        }
    ]
}
```

### `attach_alert_operation_transport`

Add a transport to every segment of an operation that does not have it yet, so the rules using that operation also deliver to it.

Route: `/api/v0/alert/operations/:id/transports`

Input (JSON):

- transport_id: the transport to add. Mandatory.
- phases: optional list of segment phases to touch (`problem`, `recovery`, `update`); every phase by default.

```curl
curl -X POST -d '{"transport_id":3}' -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert/operations/12/transports
```

Output: the operation in the same format as `list_alert_operations`, plus `changed_segments`.

### `detach_alert_operation_transport`

Remove a transport from every segment of an operation.

Route: `/api/v0/alert/operations/:id/transports/:transport_id`

```curl
curl -X DELETE -H 'Authorization: Bearer YOURAPITOKENHERE' https://foo.example/api/v0/alert/operations/12/transports/3
```

