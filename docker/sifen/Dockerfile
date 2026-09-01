# El Automatizador SIFEN. Es OTRO proyecto: el SPG sólo le habla por HTTP.
#
# Se hornea por lo mismo que los demás: montarlo del host no funciona en el
# panel. Con SIFEN_ACTIVO=false este contenedor no lo usa nadie.
FROM php:8.3-cli

ENV TZ=America/Asuncion
WORKDIR /automatizador

# No necesita Composer: no tiene dependencias ni composer.json.
COPY _sifen/ /automatizador/

EXPOSE 8090
CMD ["php", "-S", "0.0.0.0:8090", "-t", "/automatizador/public"]
