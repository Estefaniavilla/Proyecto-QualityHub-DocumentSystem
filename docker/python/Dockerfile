FROM python:3.11-slim

# Instalar dependencias del sistema y el servidor virtual de pantalla Xvfb
RUN apt-get update && apt-get install -y \
    python3-tk \
    xvfb \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Instalar pymongo
RUN pip install pymongo

# Iniciamos Xvfb y mantenemos el script escuchando de forma indefinida en la red interna
CMD ["sh", "-c", "Xvfb :99 -ac -screen 0 1024x768x16 & export DISPLAY=:99 && python buscador.py"]