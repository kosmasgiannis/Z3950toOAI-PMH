<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" exclude-result-prefixes="marc">
	<xsl:output method="text" indent="yes"/>
	<xsl:template match="/marc:record">
		<xsl:value-of select="marc:controlfield[@tag=001]"/>
	</xsl:template>

</xsl:stylesheet>
