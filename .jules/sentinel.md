## 2025-05-15 - [PostgreSQL search_path Injection]
**Vulnerability:** SQL injection via unescaped schema names in 'SET search_path' statements.
**Learning:** PostgreSQL 'SET search_path' does not support standard parameter binding. Identifiers must be manually escaped by wrapping in double quotes and doubling any internal double quotes.
**Prevention:** Use a centralized helper like 'Company::getSafeSearchPath()' to ensure all tenant switches are securely handled.
