<?php

class NotFoundException extends \Exception{}

class Html
{
  private $proxy;
  private $baseUrl;
  private $html;
  private $thisHost;
  public function __construct(Proxy $proxy, $html, $baseUrl, $thisHost)
  {
    $this->proxy = $proxy;
    $this->html = $html;
    $this->baseUrl = $baseUrl;
    $this->thisHost = $thisHost;
  }
  
  
  private function modifyA($dom)
  {
    $tags = $dom->getElementsByTagName('a');
    foreach ($tags as $tag)
    {
      $tag->setAttribute('href', $this->proxy->convertUrl($tag->getAttribute('href'), $this->baseUrl));
    }
  }
  
  private function modifyLink($dom)
  {
    $tags = $dom->getElementsByTagName('link');
    foreach ($tags as $tag)
    {
      $tag->setAttribute('href', $this->proxy->convertUrl($tag->getAttribute('href'), $this->baseUrl));
    }
  }
  
  private function modifyScript($dom)
  {
    $tags = $dom->getElementsByTagName('script');
    foreach ($tags as $tag)
    {
      $scr = $tag->getAttribute('src');
      //Script with scr
      if ($scr)
      {
        $tag->setAttribute('src', $this->proxy->convertUrl($scr, $this->baseUrl));
      }
      else
      {
        $tag->nodeValue = str_replace(str_replace('/', '\/', addslashes($this->baseUrl)), str_replace('/', '\/', addslashes($this->thisHost)), $tag->nodeValue);
      }
    }
  }
  
  private function modifyImg($dom)
  {
    $tags = $dom->getElementsByTagName('img');
    foreach ($tags as $tag)
    {
      $scr = $tag->getAttribute('src');
      $tag->setAttribute('src', $this->proxy->convertUrl($scr, $this->baseUrl));
    }
  }
  
  private function modifyForm($dom)
  {
    $tags = $dom->getElementsByTagName('form');
    foreach ($tags as $tag)
    {
      $scr = $tag->getAttribute('action');
      $tag->setAttribute('action', $this->proxy->convertUrl($scr, $this->baseUrl));
    }
  }
  
  public function proccess()
  {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($this->html);
    libxml_use_internal_errors(false);

      
    $this->modifyA($dom);
    $this->modifyScript($dom);
    $this->modifyLink($dom);
    $this->modifyImg($dom);
    $this->modifyForm($dom);
    return $dom->saveHTML();
  }
}

class Css
{
  private $proxy;
  private $baseUrl;
  private $css;
  private $thisHost;
  public function __construct(Proxy $proxy, $css, $baseUrl, $thisHost)
  {
    $this->proxy = $proxy;
    $this->css = $css;
    $this->baseUrl = $baseUrl;
    $this->thisHost = $thisHost;
  }
  
  public function proccess()
  {
    $matches = [];
    if (preg_match_all('/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i', $this->css, $matches, PREG_PATTERN_ORDER))
    {
      foreach($matches[3] as $match) 
      {
        $this->css = str_replace($match, $this->proxy->convertUrl($match, $this->baseUrl), $this->css);
      }
    }
  
    return $this->css;
  }
}


class Proxy
{
  private $baseUrl;
  private $thisUrl;
  private $thisHost;

  public function __construct($baseUrl)
  {
    $this->thisHost = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'];
    $this->thisUrl = $this->thisHost.$_SERVER['REQUEST_URI'];
    $this->baseUrl = $baseUrl;
    $loadUrl = $baseUrl.$_SERVER['REQUEST_URI'];
    
    if (isset($_GET['remote']))
    {
      $loadUrl = base64_decode(urldecode($_GET['remote']));
    }
    
    //Initial load
    $this->loadUrl($loadUrl);
  }
  
  function rel2abs($rel, $base)
  {
      /* return if already absolute URL */
      if (parse_url($rel, PHP_URL_SCHEME) != '')
          return ($rel);

      /* queries and anchors */
      if ($rel[0] == '#' || $rel[0] == '?')
          return ($base . $rel);
          
      /* parse base URL and convert to local variables: $scheme, $host, $path, $query, $port, $user, $pass */
      $path = '';
      extract(parse_url($base));

      if (strpos($rel, '//') === 0)
      {
        return $scheme.':'.$rel;
      }
      
      /* remove non-directory element from path */
      $path = preg_replace('#/[^/]*$#', '', $path);

      /* destroy path if relative url points to root */
      if ($rel[0] == '/')
          $path = '';

      /* dirty absolute URL */
      $abs = '';

      /* do we have a user in our URL? */
      if (isset($user)) {
          $abs .= $user;

          /* password too? */
          if (isset($pass))
              $abs .= ':' . $pass;

          $abs .= '@';
      }

      $abs .= $host;

      /* did somebody sneak in a port? */
      if (isset($port))
          $abs .= ':' . $port;

      $abs .= $path . '/' . $rel . (isset($query) ? '?' . $query : '');

      /* replace '//' or '/./' or '/foo/../' with '/' */
      $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
      for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {
      }

      /* absolute URL is ready! */

      return ($scheme . '://' . $abs);
  }
  
  public function convertUrl($url, $base)
  {
    $url = $this->rel2abs($url, $base);
    $parsedUrl = parse_url($url);

    if (strpos($url, $base) === false && strpos($url, $this->thisHost) === false)
    {
      return $this->thisHost.'?remote='.urlencode (base64_encode($url));
    }
    
    return $this->thisHost.(isset($parsedUrl['path']) ? $parsedUrl['path'] : '').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '').(isset($parsedUrl['fragment']) ? '#'.$parsedUrl['fragment'] : '');
  }
  
 
  private function loadUrl($url)
  {
    //Do post if we have a post data
    $post = $_POST;
    if (!empty($postData))
    {
      $postdata = http_build_query($post);

      $opts = ['http' =>
          [
              'method'  => 'POST',
              'header'  => 'Content-type: application/x-www-form-urlencoded',
              'content' => $postdata
          ]
      ];

      $context  = stream_context_create($opts);
    }
    else
    {
      $context = null;
    }
  
    
    $data = @file_get_contents($url, false, $context);
    if (!$data)
    {
      throw new NotFoundException($url);
    }
    
    foreach ($http_response_header as $contentType) 
    {
        if (preg_match('/^Content-Type:/i', $contentType)) 
        {
            // Successful match
            header($contentType,false);
            break;
        }
    }

    //It is HTML, proccess it
    if (strpos($data, '<html') !== false)
    {
      $html = new Html($this, $data, $this->baseUrl, $this->thisHost);
      echo $html->proccess();
    }
    //Css can contain URLS too
    else if (strpos($contentType, 'text/css') !== false)
    {
      $css = new Css($this, $data, $this->thisUrl, $this->thisHost);
      echo $css->proccess();
    }
    else
    {
      echo $data;
    }
  }
}

$proxy = new Proxy('http://salamek.cz');
