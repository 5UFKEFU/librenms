ARG LIBRENMS_BASE_IMAGE=librenms/librenms@sha256:0c6afaac097cd8dd26f58a7602d7b305c0e26ca974874ba196c562a8f9fc15c4
FROM ${LIBRENMS_BASE_IMAGE}

ARG VCS_REF=unknown
LABEL org.opencontainers.image.source="https://github.com/5UFKEFU/librenms" \
      org.opencontainers.image.revision="${VCS_REF}"

COPY --chown=librenms:librenms app/Api/Controllers/SensorThresholdController.php /opt/librenms/app/Api/Controllers/SensorThresholdController.php
COPY --chown=librenms:librenms app/Api/Controllers/DeviceCurrentMetricController.php /opt/librenms/app/Api/Controllers/DeviceCurrentMetricController.php
COPY --chown=librenms:librenms app/Models/Traits/HasThresholds.php /opt/librenms/app/Models/Traits/HasThresholds.php
COPY --chown=librenms:librenms app/Observers/SensorObserver.php /opt/librenms/app/Observers/SensorObserver.php
COPY --chown=librenms:librenms app/Services/CurrentRrdMetricService.php /opt/librenms/app/Services/CurrentRrdMetricService.php
COPY --chown=librenms:librenms app/Services/LiveSnmpMetricService.php /opt/librenms/app/Services/LiveSnmpMetricService.php
COPY --chown=librenms:librenms app/Models/Service.php /opt/librenms/app/Models/Service.php
COPY --chown=librenms:librenms includes/services.inc.php /opt/librenms/includes/services.inc.php
COPY --chown=librenms:librenms includes/html/api_functions.inc.php /opt/librenms/includes/html/api_functions.inc.php
COPY --chown=librenms:librenms database/migrations/2026_08_23_060000_add_service_checked_to_services_table.php /opt/librenms/database/migrations/2026_08_23_060000_add_service_checked_to_services_table.php
COPY --chown=librenms:librenms routes/api.php /opt/librenms/routes/api.php

USER root
RUN rm -f /opt/librenms/bootstrap/cache/routes.php /opt/librenms/bootstrap/cache/routes-*.php
