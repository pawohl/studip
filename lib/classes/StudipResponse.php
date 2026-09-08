<?php
class StudipResponse extends Trails\Response
{

    /**
     * Constructor.
     * @return void
     */
    public function __construct(protected Psr\Http\Message\ResponseInterface $psr_response)
    {
        parent::__construct();
    }

    /**
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __call($name, $value)
    {
        return $this->psr_response->$name($value);
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getPsrResponse(): \Psr\Http\Message\ResponseInterface
    {
        return $this->psr_response;
    }

    /**
     *
     * @return void
     */
    public function output()
    {
        $status = sprintf('HTTP/%s %s %s'
            , $this->psr_response->getProtocolVersion()
            , $this->psr_response->getStatusCode()
            , $this->psr_response->getReasonPhrase()
        );
        header($status);

        foreach ($this->psr_response->getHeaders() as $name => $values) {
            $responseHeader = sprintf('%s: %s'
                , $name
                , $this->psr_response->getHeaderLine($name)
            );
            header($responseHeader, false);
        }
        echo $this->psr_response->getBody();
    }

    /**
     * Sets the body of the response.
     *
     * @param string|Psr\Http\Message\StreamInterface $body the body
     *
     * @return static   this response object. Useful for cascading method calls.
     */
    public function set_body($body)
    {
        if ($body instanceof Psr\Http\Message\StreamInterface) {
            $this->psr_response = $this->psr_response->withBody($body);
        } else {
            $this->psr_response->getBody()->write((string)$body);
        }
        return $this;
    }


    /**
     * Sets the status code and an optional custom reason. If none is given, the
     * standard reason phrase as of RFC 2616 is used.
     *
     * @param integer  the status code
     * @param string   the custom reason, defaulting to the one given in RFC 2616
     *
     * @return static    this response object. Useful for cascading method calls.
     */
    public function set_status($status, $reason = null)
    {
        $this->psr_response = $this->psr_response->withStatus($status, $reason ?? self::get_reason($status));
        return $this;
    }



    /**
     * Adds an additional header to the response.
     *
     * @param string  the left hand key part
     * @param string  the right hand value part
     *
     * @return static   this response object. Useful for cascading method calls.
     */
    function add_header($key, $value)
    {
        $this->psr_response = $this->psr_response->withHeader($key, $value);

        if (
            strcasecmp($key, 'location') === 0
            && $this->psr_response->getStatusCode() === 200
        ) {
            $this->set_status(302);
        }

        return $this;
    }
}
