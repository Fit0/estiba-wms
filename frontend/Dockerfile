# The development image of the frontend. There is no production image yet: it arrives with the deployment.
FROM node:24-alpine AS frontend_dev

WORKDIR /app

# Installs the dependencies when package-lock.json changes, and starts what it is told to start.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["sh", "/usr/local/bin/docker-entrypoint.sh"]
CMD ["npm", "run", "dev"]

EXPOSE 5173
