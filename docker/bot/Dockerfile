FROM python:3.13-slim

WORKDIR /app

# Install dependencies if requirements.txt exists
COPY requirements.txt* ./
RUN if [ -f requirements.txt ]; then pip install --no-cache-dir -r requirements.txt; fi

# Application code is mounted as a volume, not copied
CMD ["python", "main.py", "--bot-id=1"]
