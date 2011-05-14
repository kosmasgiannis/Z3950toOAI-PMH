<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
     version="1.0"
     xmlns:marc="http://www.loc.gov/MARC21/slim"
     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
     xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
     exclude-result-prefixes="xsi">

    <xsl:output indent="yes" method="xml" version="1.0" encoding="UTF-8"/>

    <xsl:template match="/">
        <xsl:if test="marc:collection">
            <collection xmlns="http://www.loc.gov/MARC21/slim" xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">
                <xsl:for-each select="marc:collection">
                    <xsl:for-each select="marc:record">
                        <record>
                            <xsl:apply-templates select="."/>
                        </record>
                    </xsl:for-each>
                </xsl:for-each>
            </collection>
        </xsl:if>
        <xsl:if test="marc:record">
            <record xmlns="http://www.loc.gov/MARC21/slim" xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">
                <xsl:apply-templates select="./*"/>
            </record>
        </xsl:if>
    </xsl:template>

    <xsl:template match="marc:record">
            <xsl:apply-templates select="@*|node()"/>
    </xsl:template>

    <xsl:template match="node()|@*">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>

</xsl:stylesheet>
