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

# Personal same-axis traffic graphs and all shared/specialized renderers.
COPY --chown=librenms:librenms app/Http/Controllers/UserPreferencesController.php /opt/librenms/app/Http/Controllers/UserPreferencesController.php
COPY --chown=librenms:librenms includes/html/functions.inc.php /opt/librenms/includes/html/functions.inc.php
COPY --chown=librenms:librenms includes/html/graphs/bill/bits.inc.php /opt/librenms/includes/html/graphs/bill/bits.inc.php
COPY --chown=librenms:librenms includes/html/graphs/device/ipSystemStats.inc.php /opt/librenms/includes/html/graphs/device/ipSystemStats.inc.php
COPY --chown=librenms:librenms includes/html/graphs/device/ipsystemstats_ipv4.inc.php /opt/librenms/includes/html/graphs/device/ipsystemstats_ipv4.inc.php
COPY --chown=librenms:librenms includes/html/graphs/device/ipsystemstats_ipv6.inc.php /opt/librenms/includes/html/graphs/device/ipsystemstats_ipv6.inc.php
COPY --chown=librenms:librenms includes/html/graphs/generic_multi_bits.inc.php /opt/librenms/includes/html/graphs/generic_multi_bits.inc.php
COPY --chown=librenms:librenms includes/html/graphs/generic_multi_seperated.inc.php /opt/librenms/includes/html/graphs/generic_multi_seperated.inc.php
COPY --chown=librenms:librenms includes/html/graphs/multiport/bits_duo.inc.php /opt/librenms/includes/html/graphs/multiport/bits_duo.inc.php
COPY --chown=librenms:librenms includes/html/graphs/multiport/bits_trio.inc.php /opt/librenms/includes/html/graphs/multiport/bits_trio.inc.php
COPY --chown=librenms:librenms includes/html/graphs/port/mac_acc_total.inc.php /opt/librenms/includes/html/graphs/port/mac_acc_total.inc.php
COPY --chown=librenms:librenms includes/html/graphs/qfp/avgpktsize.inc.php /opt/librenms/includes/html/graphs/qfp/avgpktsize.inc.php
COPY --chown=librenms:librenms lang/en/preferences.php /opt/librenms/lang/en/preferences.php
COPY --chown=librenms:librenms lang/zh-CN/preferences.php /opt/librenms/lang/zh-CN/preferences.php
COPY --chown=librenms:librenms lang/zh-TW/preferences.php /opt/librenms/lang/zh-TW/preferences.php
COPY --chown=librenms:librenms resources/views/user/preferences.blade.php /opt/librenms/resources/views/user/preferences.blade.php

COPY --chown=librenms:librenms includes/html/graphs/generic_multi_data.inc.php /opt/librenms/includes/html/graphs/generic_multi_data.inc.php
COPY --chown=librenms:librenms includes/html/graphs/generic_duplex.inc.php /opt/librenms/includes/html/graphs/generic_duplex.inc.php

USER root
RUN rm -f /opt/librenms/bootstrap/cache/routes.php /opt/librenms/bootstrap/cache/routes-*.php
