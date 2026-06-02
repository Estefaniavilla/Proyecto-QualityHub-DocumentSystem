FROM python:3.11-slim

WORKDIR /app

# Instalar las librerías necesarias directamente
RUN pip install --no-cache-dir flask pymongo pdfplumber python-docx openpyxl

# Copiar el contenido al directorio de trabajo
COPY . /app

EXPOSE 5000

# El comando correcto: el archivo está en la raíz de la carpeta montada
CMD ["python", "buscador.py"]