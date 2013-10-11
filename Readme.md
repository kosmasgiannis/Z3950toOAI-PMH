Z39.50toOAI-PMH is a lightweight gateway that can be configured to handle 
OAI-PMH requests in front of a set of Z39.50 servers.

The gateway can be configured for each individual repository to handle 
multiple sets, multiple metadata formats, use resumption tokens and 
filter records based on date limits.

Each set is related to a query against the Z39.50 server and the retrieved
records are processed with stylesheets in order to be presented in the
requested metadata format.

Copyright (C) 2011 Giannis Kosmas. See the file LICENSE for details.
