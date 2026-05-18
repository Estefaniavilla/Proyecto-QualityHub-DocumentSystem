FROM python:3.11-slim

WORKDIR /app

# Instalar Flask para levantar el servidor web y Pymongo para conectarse a MongoDB
RUN pip install flask pymongo

COPY . /app

EXPOSE 5000

CMD ["python", "buscador.py"]